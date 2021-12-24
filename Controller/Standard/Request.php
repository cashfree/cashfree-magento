<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Cashfree\Cfcheckout\Model\PaymentMethod;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Request
 * Generate request datat to create order and proceed payment
 * @package Cashfree\Cfcheckout\Controller\Standard\Notify
 */
class Request extends \Cashfree\Cfcheckout\Controller\CfAbstract
{
    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Cashfree\Cfcheckout\Model\Config $config
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Cashfree\Cfcheckout\Model\PaymentMethod $paymentMethod
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $config
        );

        $this->logger           = $logger;
        $this->cache            = $cache;
        $this->config           = $config;
        $this->customerSession  = $customerSession;
        $this->paymentMethod    = $paymentMethod;
        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
    }

    /**
     * Get user token for process the payment
     * @return array
     */
    public function execute()
    {
        $quote_id = $this->getQuote()->getId();

        //validate shipping, billing and phone number
        $validationSuccess =  true;
        $code = 200;

        if(empty($_POST['email']) === true)
        {
            $this->logger->info("Email field is required");

            $responseContent = [
                'message'   => "Email field is required",
                'parameters' => []
            ];

            $validationSuccess = false;
        }

        if(empty($this->getQuote()->getBillingAddress()->getPostcode()) === true)
        {
            $responseContent = [
                'message'   => "Billing Address is required",
                'parameters' => []
            ];

            $validationSuccess = false;
        }

        $getCustomentNumber = $this->getQuote()->getBillingAddress()->getTelephone();

        $customerPhone = substr($getCustomentNumber, -10);

        $mobileDigitsLength = strlen($customerPhone);

        if ($mobileDigitsLength == 10) {
            if (!preg_match("/^[6-9][0-9]{9}$/", $customerPhone)) {
                $responseContent = [
                    'message'   => "Customer phone number is not valid.",
                    'parameters' => []
                ];
                $validationSuccess = false;
              }
        } else {
            $responseContent = [
                'message'   => "Customer phone number is not valid.",
                'parameters' => []
            ];

            $validationSuccess = false;
        }

        if(!$this->getQuote()->getIsVirtual())
        {
            //validate quote Shipping method
            if(empty($this->getQuote()->getShippingAddress()->getShippingMethod()) === true)
            {
                $responseContent = [
                    'message'   => "Shipping method is required",
                    'parameters' => []
                ];

                $validationSuccess = false;
            }

            if(empty($this->getQuote()->getShippingAddress()->getPostcode()) === true)
            {
                $responseContent = [
                    'message'   => "Shipping Address is required",
                    'parameters' => []
                ];

                $validationSuccess = false;
            }
        }

        if($validationSuccess)
        {
            $amount = round($this->getQuote()->getGrandTotal(), 2);

            $cashfreeOrderId = $quote_id."_".time();

            if (!$this->customerSession->isLoggedIn()) {
                $this->getQuote()->setCustomerEmail($_POST['email']);
                $this->getQuote()->save();
            }

            $this->customerSession->setCustomerEmailAddress($_POST['email']);

            $params = array(
                "customer_details" => array(
                    "customer_id" => "MagentoCustomer",
                    "customer_email" => $_POST['email'],
                    "customer_phone"=> $customerPhone
                ),
                "order_id" => $cashfreeOrderId,
                "order_amount" => $amount,
                "order_currency" => $this->getQuote()->getQuoteCurrencyCode(),
                "order_note" => "Magento Order",
                "order_meta"=> array(
                    "notify_url" => $this->paymentMethod->getNotifyUrl()
                )
            );

            $curlPostfield = json_encode($params);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->getOrderUrl(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $curlPostfield,
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

            if ($err) {
                $responseContent = [
                    'message'   => 'Unable to create your order. Please contact support.',
                    'parameters' => []
                ];
            }

            $order = json_decode($response);
            if (null !== $order && !empty($order->order_token))
            {
                $responseContent = [
                    'success'           => true,
                    'cashfree_order'    => $order->cf_order_id,
                    'order_id'          => $quote_id,
                    'order_token'       => $order->order_token,
                    'amount'            => $order->order_amount,
                    'quote_currency'    => $this->getQuote()->getQuoteCurrencyCode(),
                    'quote_amount'      => number_format($this->getQuote()->getGrandTotal(), 2, ".", ""),
                    'environment'       => $this->config->getConfigData("environment"),
                ];

                $this->checkoutSession->setCashfreeOrderID($order->cf_order_id);
                $this->checkoutSession->setCashfreeOrderAmount($amount);

                //save to cashfree orderLink
                $orderLinkCollection = $this->_objectManager->get('Cashfree\Cfcheckout\Model\OrderLink')
                                                        ->getCollection()
                                                        ->addFilter('quote_id', $quote_id)
                                                        ->getFirstItem();

                $orderLinkData = $orderLinkCollection->getData();
                
                if (empty($orderLinkData['entity_id']) === false)
                {
                    $orderLinkCollection->setCfOrderId($cashfreeOrderId)
                                ->save();
                }
                else
                {
                    $orderLnik = $this->_objectManager->create('Cashfree\Cfcheckout\Model\OrderLink');
                    $orderLnik->setQuoteId($quote_id)
                                ->setCfOrderId($cashfreeOrderId)
                                ->save();
                }

            } else {
                $responseContent = [
                    'message'   => 'Unable to create your order. Please contact support.',
                    'parameters' => []
                ];
            }
            
        }
        $this->cache->save("started", "quote_Front_processing_$quote_id", ["cashfree"], 300);

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode($code);

        return $response;
        
    }

    /**
     * Get order url for order processing
     * @return string
     */
    protected function getOrderUrl()
    {
        $environment = $this->config->getConfigData("environment");

        $orderUrl = $this->config->getConfigData('test_url');

        if ($environment === 'production') {
            $orderUrl = $this->config->getConfigData('prod_url');
        }
        return $orderUrl;
    }

}