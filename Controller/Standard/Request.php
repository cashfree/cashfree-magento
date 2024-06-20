<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Cashfree\Cfcheckout\Controller\CfAbstract;
use Cashfree\Cfcheckout\Helper\Cfcheckout;
use Cashfree\Cfcheckout\Model\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Transaction;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Class Request
 * Generate request data to create order and proceed payment
 * @package Cashfree\Cfcheckout\Controller\Standard\Notify
 */
class Request extends CfAbstract
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Context
     */

    protected $context;

    /**
     * @var Cfcheckout
     */

    protected $helper;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Context $context
     * @param Cfcheckout $helper
     * @param Transaction $transaction
     * @param Session $checkoutSession
     * @param InvoiceService $invoiceService
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Context $context,
        Cfcheckout $helper,
        Transaction $transaction,
        Session $checkoutSession,
        InvoiceService $invoiceService,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender
    ) {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $checkoutSession,
            $invoiceService,
            $quoteRepository,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );

        $this->helper   = $helper;
    }

    /**
     * Get order token for process the payment
     * @return ResultInterface
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $cashfreeOrderId = preg_replace("/[^a-zA-Z0-9_-]/", $this->config->getConfigData('order_id_replacement_char') ?? '-', $order->getIncrementId());
        $new_order_status = $this->config->getNewOrderStatus();

        $magento_version = $this->_objectManager->get('Magento\Framework\App\ProductMetadataInterface')->getVersion();
        $module_version =  $this->_objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Cashfree_Cfcheckout')['setup_version'];

        $orderModel = $this->_objectManager->get('Magento\Sales\Model\Order')->load($order->getEntityId());

        $orderModel->setState('new')
                   ->setStatus($new_order_status)
                   ->save();

        $code = 400;
        $countryCode = "";
        if(empty($order->getShippingAddress())) {
            $countryId = $order->getBillingAddress()->getCountryId();
            $getCustomerNumber = $order->getBillingAddress()->getTelephone();
        } else {
            $countryId = $order->getShippingAddress()->getCountryId();
            $getCustomerNumber = $order->getShippingAddress()->getTelephone();
        }

        if(!empty($countryId)){
            $countryCode = $this->helper->getPhoneCode($countryId);
        }


        $customerNumber = preg_replace("/[^0-9]/", '', $getCustomerNumber);

        if($countryCode != ""){
            $customerNumber = "+".$countryCode.$customerNumber;
        }

        $email = $order->getCustomerEmail();

        if(!empty($email))
        {
            $getCfOrder = $this->getCfOrderResponse($cashfreeOrderId);
            $amount = round($order->getGrandTotal(), 2);
            if (isset($getCfOrder->order_status)) {
                if ($getCfOrder->order_status !== 'PAID') {
                    if (number_format($getCfOrder->order_amount, 2) == number_format($amount, 2) &&
                        strtoupper($getCfOrder->order_currency) === strtoupper($order->getOrderCurrencyCode())) {
                        $code = 200;
                        $responseContent = [
                            'success'               => true,
                            'cashfree_order'        => $getCfOrder->cf_order_id,
                            'order_id'              => $cashfreeOrderId,
                            'payment_session_id'    => $getCfOrder->payment_session_id,
                            'amount'                => $getCfOrder->order_amount,
                            'order_currency'        => $order->getOrderCurrencyCode(),
                            'order_amount'          => $amount,
                            'environment'           => $this->config->getCfEnvironment(),
                            'magento_version'       => $magento_version,
                            'module_version'        => $module_version,
                        ];
                    } else {
                        $responseContent = [
                            'message'       => 'Unable to create your order. Please contact support.',
                            'parameters'    => []
                        ];
                    }
                } else {
                    $responseContent = [
                        'message'       => 'Unable to create your order. Please create another order or contact support.',
                        'parameters'    => []
                    ];
                }
            } else {
                $params = array(
                    "customer_details"      => array(
                        "customer_id"       => "MagentoCustomer",
                        "customer_email"    => $email,
                        "customer_phone"    => $customerNumber
                    ),
                    "order_id"              => $cashfreeOrderId,
                    "order_amount"          => $amount,
                    "order_currency"        => $order->getOrderCurrencyCode(),
                    "order_note"            => "Magento Order",
                    "order_meta"            => array(
                        "return_url"        => $this->config->getReturnUrl(),
                        "notify_url"        => $this->config->getNotifyUrl()
                    )
                );

                $curlPostField = json_encode($params);

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL             => $this->getOrderUrl(),
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_ENCODING        => "",
                    CURLOPT_MAXREDIRS       => 10,
                    CURLOPT_TIMEOUT         => 30,
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST   => "POST",
                    CURLOPT_POSTFIELDS      => $curlPostField,
                    CURLOPT_HTTPHEADER      => [
                        "Accept:            application/json",
                        "Content-Type:      application/json",
                        "x-api-version:     ".self::API_VERSION_20220901,
                        "x-client-id:       ".$this->config->getAppId(),
                        "x-client-secret:   ".$this->config->getSecretKey(),
                        "x-idempotency-key: ".$cashfreeOrderId
                    ],
                ]);

                $response = curl_exec($curl);

                curl_close($curl);

                $cfOrder = json_decode($response);

                if (null !== $cfOrder && !empty($cfOrder->payment_session_id))
                {
                    $code = 200;
                    $responseContent = [
                        'success'               => true,
                        'cashfree_order'        => $cfOrder->cf_order_id,
                        'order_id'              => $cashfreeOrderId,
                        'payment_session_id'    => $cfOrder->payment_session_id,
                        'amount'                => $cfOrder->order_amount,
                        'order_currency'        => $order->getOrderCurrencyCode(),
                        'order_amount'          => $amount,
                        'environment'           => $this->config->getCfEnvironment(),
                        'magento_version'       => $magento_version,
                        'module_version'        => $module_version,
                    ];
                } else {
                    $responseContent = [
                        'message'       => 'Unable to create your order. Please contact support.',
                        'parameters'    => []
                    ];
                }
            }
        } else {
            $responseContent = [
                'message'       => 'Email is mandatory. Please add a valid email.',
                'parameters'    => []
            ];
        }

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode($code);

        return $response;

    }

}
