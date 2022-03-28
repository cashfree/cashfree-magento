<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Request
 * Generate request datat to create order and proceed payment
 * @package Cashfree\Cfcheckout\Controller\Standard\Notify
 */
class Request extends \Cashfree\Cfcheckout\Controller\CfAbstract
{
    /**
     * @var \Psr\Log\LoggerInterface 
     */
    protected $logger;

    /**
     * @var \Cashfree\Cfcheckout\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Action\Context
     */

    protected $context;

    /**
     * @var \Cashfree\Cfcheckout\Helper\Cfcheckout
     */

    protected $helper;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Customer\Model\Session
    */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Cashfree\Cfcheckout\Model\Config $config
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Cashfree\Cfcheckout\Helper\Cfcheckout $helper
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\Action\Context $context,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $helper,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $customerSession,
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
     * @return array
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $cashfreeOrderId = $order->getIncrementId();
        $new_order_status = $this->config->getNewOrderStatus();
        $orderModel = $this->_objectManager->get('Magento\Sales\Model\Order')->load($order->getEntityId());

        $orderModel->setState('new')
                   ->setStatus($new_order_status)
                   ->save();
        
        $code = 400;
        $countryCode = "";
        if(empty($order->getShippingAddress())) {
            $countryId = $order->getBillingAddress()->getCountryId();
            $getCustomentNumber = $order->getBillingAddress()->getTelephone();
        } else {
            $countryId = $order->getShippingAddress()->getCountryId();
            $getCustomentNumber = $order->getShippingAddress()->getTelephone();
        }

        if(isset($countryId) && !empty($countryId)){
            $countryCode = $this->helper->getPhoneCode($countryId);
        }

        
        $customerNumber = preg_replace("/[^0-9]/", '', $getCustomentNumber);

        if($countryCode != ""){
            $customerNumber = "+".$countryCode.$customerNumber;
        }

        $email = $order->getCustomerEmail();

        if(isset($email) && !empty($email))
        {
            $amount = round($order->getGrandTotal(), 2);

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
        
            $curlPostfield = json_encode($params);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL             => $this->getOrderUrl(),
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_ENCODING        => "",
                CURLOPT_MAXREDIRS       => 10,
                CURLOPT_TIMEOUT         => 30,
                CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST   => "POST",
                CURLOPT_POSTFIELDS      => $curlPostfield,
                CURLOPT_HTTPHEADER      => [
                    "Accept:            application/json",
                    "Content-Type:      application/json",
                    "x-api-version:     2021-05-21",
                    "x-client-id:       ".$this->config->getConfigData('app_id'),
                    "x-client-secret:   ".$this->config->getConfigData('secret_key'),
                    "x-idempotency-key: ".$cashfreeOrderId
                ],
            ]);

            $response = curl_exec($curl);
            
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                $responseContent = [
                    'message'       => 'Unable to create your order. Please contact support.',
                    'parameters'    => []
                ];
            }

            $cfOrder = json_decode($response);

            if (null !== $cfOrder && !empty($cfOrder->order_token))
            {
                $code = 200;

                $responseContent = [
                    'success'           => true,
                    'cashfree_order'    => $cfOrder->cf_order_id,
                    'order_id'          => $cashfreeOrderId,
                    'order_token'       => $cfOrder->order_token,
                    'payment_link'       => $cfOrder->payment_link,
                    'amount'            => $cfOrder->order_amount,
                    'order_currency'    => $order->getOrderCurrencyCode(),
                    'order_amount'      => $amount,
                    'environment'       => $this->config->getConfigData("environment"),
                ];
                
        
            } else {
                $responseContent = [
                    'message'       => 'Unable to create your order. Please contact support.',
                    'parameters'    => []
                ];
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