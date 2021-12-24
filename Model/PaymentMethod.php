<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Cashfree\Cfcheckout\Model\Config;
use Magento\Catalog\Model\Session;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod {

    const METHOD_CODE = 'cashfree';
   
    const ACTION_PROCESSING = 'processing';

    protected $_code = self::METHOD_CODE;

    /**
     * @var bool
     */
    protected $_canProcessing   = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout  = true;

     /**
     * @var \Cashfree\Cfcheckout\Model\Config
     */
    protected $config;
    
    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */

      public function __construct(
        \Magento\Framework\Registry $registry,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\Model\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Payment\Model\Method\Logger $logger,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $helper,
        \Magento\Framework\DB\Transaction $transaction,  
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->config           = $config;
        $this->helper           = $helper;
        $this->request          = $request;
        $this->transaction      = $transaction;
        $this->checkoutSession  = $checkoutSession;
        $this->invoiceService   = $invoiceService;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }
    
    /**
     * Get redirect url
     *
     * @return void
     */
    public function getRedirectUrl() {
        return $this->helper->getUrl($this->getConfigData('redirect_url'));
    }
    
    /**
     * Get return url
     *
     * @return void
     */
    public function getReturnUrl() {
        return $this->helper->getUrl($this->getConfigData('return_url'));
    }
    
    /**
     * Get notify url
     *
     * @return void
     */
    public function getNotifyUrl() {
        return $this->helper->getUrl($this->getConfigData('notify_url'),array('_secure'=>true));
    }

    /**
     * Return url according to environment
     * @return string
     */
    public function getCgiUrl() {
        $env = $this->getConfigData('environment');
        if ($env === 'production') {
            return $this->getConfigData('prod_url');
        }
        return $this->getConfigData('test_url');
    }

    //Authorize the payment recieved from cashfree
    public function authorize(InfoInterface $payment, $amount) {
        $request = $this->getPostData();
        $isWebhookCall = false;
        if((empty($_POST) === false) and (isset($_POST['signature']) === true))
        {
            //set request data based on notify flow
            if($_POST['txStatus'] == 'SUCCESS') {
                $cfOrderStatus = 'PAID';
            } else {
                $cfOrderStatus = 'ACTIVE';
            }
            $request['paymentMethod']['additional_data'] = [
                'cf_transaction_id' => $_POST['referenceId'],
                'cf_order_id' => $_POST['orderId'],
                'cf_transaction_amount' => $_POST['orderAmount'],
                'cf_order_status' => $cfOrderStatus
            ];
            $isWebhookCall = true;
        }
        
        $transactionId = $request['paymentMethod']['additional_data']['cf_transaction_id'];
        if(empty($transactionId) === false && $request['paymentMethod']['additional_data']['cf_order_status'] === 'PAID')
        {
            $order = $payment->getOrder();
            $orderAmount = round($order->getGrandTotal(), 2);
            $orderId = $order->getIncrementId();
            $cfOrderAmount = $request['paymentMethod']['additional_data']['cf_transaction_amount'];
            if ($orderAmount != $cfOrderAmount)
            {
                throw new LocalizedException(__("Cart order amount = %1 doesn't match with amount paid = %2", $orderAmount, $cfOrderAmount));
            }
            $this->validateSignature($request, $order);

            $payment->setStatus(self::STATUS_APPROVED)
                ->setAmountPaid($orderAmount)
                ->setLastTransId($transactionId)
                ->setTransactionId($transactionId)
                ->setIsTransactionClosed(true)
                ->setShouldCloseParentTransaction(true);
            $cfOrderId = $request['paymentMethod']['additional_data']['cf_order_id'];

            //update the Cashfree payment with corresponding created order ID of this quote ID
            $this->updatePaymentNote($transactionId, $order, $cfOrderId,$isWebhookCall);

        } else {
            throw new LocalizedException(__("Cashfree Payment details missing."));
        }
        
    }

    /**
     * Update the payment note with Magento frontend OrderID
     *
     * @param string $transactionId
     * @param object $salesOrder
     * @param object $cfOrderId
     * @param object $isWebhookCall
     */
    protected function updatePaymentNote($transactionId, $order, $cfOrderId, $isWebhookCall)
    {
        //update orderLink
        $_objectManager  = \Magento\Framework\App\ObjectManager::getInstance();

        $orderLinkCollection = $_objectManager->get('Cashfree\Cfcheckout\Model\OrderLink')
                                                   ->getCollection()
                                                   ->addFieldToSelect('entity_id')
                                                   ->addFilter('quote_id', $order->getQuoteId())
                                                   ->addFilter('cf_order_id', $cfOrderId)
                                                   ->getFirstItem();

        $orderLink = $orderLinkCollection->getData();

        if (empty($orderLink['entity_id']) === false)
        {
            $orderAmount = round($order->getGrandTotal(), 2);
            $orderLinkCollection->setCfReferenceId($transactionId)
                                ->setIncrementOrderId($order->getIncrementId())
                                ->setCfOrderAmount($orderAmount);

            if ($isWebhookCall)
            {
                $orderLinkCollection->setByNotify(true)->save();
            }
            else
            {
                $orderLinkCollection->setByFrontend(true)->save();
            }
            
        }

    }
    
    //Validate order is paid or not from cashfree order api
    protected function validateSignature($request, $order)
    {
        $cfOrderId = $request['paymentMethod']['additional_data']['cf_order_id'];
        $cfOrderStatus = $request['paymentMethod']['additional_data']['cf_order_status'];

        $getOrderUrl = $this->getCgiUrl()."/".$cfOrderId;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $getOrderUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "x-api-version: 2021-05-21",
                "x-client-id: ".$this->config->getConfigData('app_id'),
                "x-client-secret: ".$this->config->getConfigData('secret_key')
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $order = json_decode($response);

        if (null !== $order && !empty($order->order_status))
        {
            if($cfOrderStatus !== $order->order_status && $order->order_status !== 'PAID') {
                throw new LocalizedException(__('Cashfree Error: Signature mismatch.'));
            }
        } else {
            throw new LocalizedException(__('Cashfree Error: Order does not found. Please contact to merchant for support.'));
        }


    }

    protected function getPostData()
    {
        $request = file_get_contents('php://input');

        return json_decode($request, true);
    }

    public function enabledInvoiceing()
    {
        return $this->getConfigData('enable_invoice');
    }

}
