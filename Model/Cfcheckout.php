<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Sales\Api\Data\TransactionInterface;

class Cfcheckout extends \Magento\Payment\Model\Method\AbstractMethod {

    const PAYMENT_CFCHECKOUT_CODE = 'cfcheckout';
   

    protected $_code = self::PAYMENT_CFCHECKOUT_CODE;

    /**
     *
     * @var \Magento\Framework\UrlInterface 
     */
    protected $_urlBuilder;
    
    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

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
        \Magento\Framework\Model\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Payment\Model\Method\Logger $logger,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $helper,
        \Magento\Framework\DB\Transaction $transaction,    
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
    ) {
        $this->helper = $helper;
        $this->httpClientFactory = $httpClientFactory;
        $this->checkoutSession = $checkoutSession;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->orderSender = $orderSender;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
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
        return $this->helper->getUrl($this->getConfigData('notify_url'));
    }

    /**
     * Return url according to environment
     * @return string
     */
    public function getCgiUrl() {
        $env = $this->getConfigData('environment');
        if ($env === 'prod') {
            return $this->getConfigData('prod_url');
        }
        return $this->getConfigData('test_url');
    }
    
    /**
     * Build checkout request
     *
     * @return void
     */
    public function buildCheckoutRequest() {
        $quote = $this->checkoutSession->getQuote();
        $billing_address = $quote->getBillingAddress();

        $params = array();
        $params["appId"] = $this->getConfigData("app_id");
        $params["orderId"] = $quote->getEntityId()."_".time();
        $params["orderAmount"] = round($quote->getGrandTotal(), 2);
        $params["orderCurrency"] = $quote->getQuoteCurrencyCode();
        $params["customerName"] = $billing_address->getFirstName(). " ". $billing_address->getLastName();

        $params["customerEmail"] = $quote->getCustomerEmail();
        $params["customerPhone"] = $billing_address->getTelephone();
    
        $params["notifyUrl"] = $this->getNotifyUrl();
        // $params["paymentModes"] = "upi";
        
        $params["returnUrl"] = $this->getReturnUrl();
        $params["source"] = "magento";
        $params["signature"] = $this->generateCFSignature($params);
        return $params;
    }
    
    /**
     * Generate CF Signature
     *
     * @param  mixed $params
     * @return void
     */
    public function generateCFSignature($params) {
        $secretKey = $this->getConfigData('secret_key');
        ksort($params);
        $signatureData = "";
        foreach ($params as $key => $value){
           $signatureData .= $key.$value;
        }
        $signature = hash_hmac('sha256', $signatureData, $secretKey, true);
        return base64_encode($signature);
    }
    
    /**
     * Validate response
     *
     * @param  mixed $returnParams
     * @return void
     */
    public function validateResponse($returnParams) {
          $orderId = $returnParams["orderId"];
          $orderAmount = $returnParams["orderAmount"];   
          $paymentMode = $returnParams["paymentMode"];  
          $referenceId = $returnParams["referenceId"];   
          $txStatus = $returnParams["txStatus"]; 
          $txTime = $returnParams["txTime"]; 
          $txMsg = $returnParams["txMsg"];
          $signature = $returnParams["signature"];
                      
          $secretKey = $this->getConfigData('secret_key');

          $data = $orderId.$orderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;
          $hash_hmac = hash_hmac('sha256', $data, $secretKey, true) ;
          $computedSignature = base64_encode($hash_hmac); 
          
          if ($computedSignature != $signature) {
            return "INVALID";
          }

          return $returnParams["txStatus"];

    }
    
    /**
     * Update order status and transaction id to order
     *
     * @param  mixed $order
     * @return void
     */
    public function postProcessing(\Magento\Sales\Model\Order $order,
            \Magento\Framework\DataObject $payment, $response) {
    
        $payment->setTransactionId($response['referenceId']);
        $payment->setTransactionAdditionalInfo('Transaction Message', $response['txMsg']);
        $payment->setAdditionalInformation('cashfree_payment_status', 'approved');
        $payment->addTransaction(TransactionInterface::TYPE_ORDER);
        $payment->setIsTransactionClosed(0);
        $payment->place();
        $order->setStatus('processing');
        $order->setState('processing');
        $order->save();
        $this->orderSender->send($order, true);
        $this->generateInvoice($order);
    }

    protected function generateInvoice($order)
    {
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->save();
        $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
            ->setIsCustomerNotified(true)
            ->save();
    }

    public function enabledDebugLog()
    {
        return $this->getConfigData('enable_debug');
    }

}
