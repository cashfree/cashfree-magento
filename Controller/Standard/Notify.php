<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Magento\Framework\DataObject;
use Cashfree\Cfcheckout\Model\Config;
use Magento\Framework\App\RequestInterface;
use Cashfree\Cfcheckout\Model\PaymentMethod;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;


/**
 * Class Notify
 * To notify customer when if there is any netword falure during payment
 * @package Cashfree\Cfcheckout\Controller\Standard\Notify
 */
class Notify extends \Cashfree\Cfcheckout\Controller\CfAbstract {

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;
    
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote;

    /**
     * @var \Tco\Checkout\Helper\Checkout
     */
    protected $_checkoutHelper;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;
    
    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;
    
    /** 
     * @var mixed
     */
    protected $objectManagement;
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;
    
    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    const STATUS_APPROVED = 'APPROVED';

    /**
     * @param \Psr\Log\LoggerInterface $logger,
     * @param \Cashfree\Cfcheckout\Model\Config $config 
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Cashfree\Cfcheckout\Model\PaymentMethod $paymentMethod
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository,
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagement
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) 
    {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $config
        );

        $this->order            = $order;
        $this->cache            = $cache;
        $this->logger           = $logger;
        $this->storeManagement  = $storeManagement;
        $this->quoteRepository  = $quoteRepository;
        $this->quoteManagement  = $quoteManagement;
        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
    }
    
    /**
     * Execute webhook in case of network failure
     *
     * @return void
     */
    public function execute() {
        $params = $this->getRequest()->getParams();
        $referenceId    = $params['referenceId'];
        $quoteId = strip_tags($params["orderId"]);
        list($quoteId) = explode('_', $quoteId);

        try
        {
            $orderLinkCollection = $this->_objectManager->get('Cashfree\Cfcheckout\Model\OrderLink')
                                                        ->getCollection()
                                                        ->addFilter('quote_id', $quoteId)
                                                        ->addFilter('cf_order_id', $params["orderId"])
                                                        ->getFirstItem();

            $orderLink = $orderLinkCollection->getData();

            if (empty($orderLink['entity_id']) === false)
            {
                if ($orderLink['order_placed'])
                {
                    $this->logger->info(__("Cashfree Notify: Quote order is inactive for quoteID: $quoteId and Cashfree reference_id(:$referenceId) with Maze OrderID (:%1) ", $orderLink['increment_order_id']));

                    return;
                }

                //set the 1st webhook notification time
                if ($orderLink['notify_count'] < 1)
                {
                    $orderLinkCollection->setWebhookFirstNotifiedAt(time());
                }

                $orderLinkCollection->setNotifyCount($orderLink['notify_count'] + 1)
                                    ->setCfReferenceId($referenceId)
                                    ->save();


                // Check if front-end cache flag active
                if (empty($this->cache->load("quote_Front_processing_".$quoteId)) === false)
                {
                    $this->logger->info("Cashfree Notify: Order processing is active for quoteID: $quoteId and Cashfree reference_id(:$referenceId)");
                    header('Status: 409 Conflict, too early for processing', true, 409);

                    exit;
                }

                $notifyWaitTime = $this->config->getConfigData(Config::NOTIFY_WAIT_TIME) ? $this->config->getConfigData(Config::NOTIFY_WAIT_TIME) : 300;

                //ignore notify call for some time as per config, from first notify call
                if ((time() - $orderLinkCollection->getNotifyFirstNotifiedAt()) < $notifyWaitTime)
                {
                    $this->logger->info(__("Cashfree Notify: Order processing is active for quoteID: $quoteId and Cashfree reference_id(:$referenceId) and notify attempt: %1", ($orderLink['notify_count'] + 1)));
                    header('Status: 409 Conflict, too early for processing', true, 409);

                    exit;
                }
            }

            if (empty($this->cache->load("quote_Front_processing_".$quoteId)) === false)
            {
                $this->logger->info("Cashfree Notify: Order processing is active for quoteID: $quoteId");
                header('Status: 409 Conflict, too early for processing', true, 409);
                exit;
            }

            $cfOrderAmount    = round($params['orderAmount'], 2);

            $this->logger->info("Cashfree Notify processing started for cashfree reference_id(:$referenceId)");

            //validate if the quote Order is still active
            $quote = $this->quoteRepository->get($quoteId);

            //exit if quote is not active
            if (!$quote->getIsActive())
            {
                $this->logger->info("Cashfree Notify: Quote order is inactive for quoteID: $quoteId and Cashfree reference_id(:$referenceId)");

                return;
            }

            //validate amount before placing order
            $quoteAmount = (round($quote->getGrandTotal(), 2));

            if ($quoteAmount != $cfOrderAmount)
            {
                $this->logger->critical("Cashfree Notify: Amount processed for payment doesn't match with store order amount for Cashfree reference_id(:$referenceId)");

                return;
            }

            # fetch the related sales order and verify the payment ID with cashfree reference id
            # To avoid duplicate order entry for same quote 
            $collection = $this->objectManagement->get('Magento\Sales\Model\Order')
                        ->getCollection()
                        ->addFieldToSelect('entity_id')
                        ->addFilter('quote_id', $quoteId)
                        ->getFirstItem();

            $salesOrder = $collection->getData();

            if (empty($salesOrder['entity_id']) === false)
            {
                $order = $this->order->load($salesOrder['entity_id']);
                $orderReferenceId= $order->getPayment()->getLastTransId();

                if ($orderReferenceId === $referenceId)
                {
                    $this->logger->info("Cashfree Notify: Sales Order and payment already exist for Cashfree reference_id(:$referenceId)");
                    return;
                }
            }

            $quote = $this->getQuoteObject($params, $quoteId);

            $this->logger->info("Cashfree Notify: Order creation started with quoteID:$quoteId.");

            //validate if the quote Order is still active
            $quoteUpdated = $this->quoteRepository->get($quoteId);

            //exit if quote is not active
            if (!$quoteUpdated->getIsActive())
            {
                $this->logger->info("Cashfree Notify: Quote order is inactive for quoteID: $quoteId and Cashfree reference_id(:$referenceId)");

                return;
            }

            if (empty($orderLink['entity_id']) === false)
            {
                if ($orderLink['order_placed'])
                {
                    $this->logger->info(__("Cashfree Notify: Quote order is inactive for quoteID: $quoteId and Cashfree reference_id(:$referenceId) with Maze OrderID (:%1) ", $orderLink['increment_order_id']));

                    return;
                }
            }

            //Now start processing the new order creation through notify

            $this->cache->save("started", "quote_processing_$quoteId", ["cashfree"], 30);

            $this->logger->info("Cashfree Notify: Quote submitted for order creation with quoteID:$quoteId.");

            $order = $this->quoteManagement->submit($quote);

            $payment = $order->getPayment();

            $this->logger->info("Cashfree Notify: Adding payment to order for quoteID:$quoteId.");

            $payment->setAmountPaid($cfOrderAmount)
                    ->setLastTransId($referenceId)
                    ->setTransactionId($referenceId)
                    ->setIsTransactionClosed(true)
                    ->setShouldCloseParentTransaction(true);

            //set cashfree notify fields
            $order->setByCashfreeNotify(1);

            $order->save();

            //disable the quote
            $quote->setIsActive(0)->save();

            $this->logger->info("Cashfree Notify Processed successfully for Cashfree reference_id(:$referenceId): and quoteID(: $quoteId) and OrderID(: ". $order->getEntityId() .")");
            return;
        }
        catch (\Exception $e)
        {
            $this->logger->critical("Cashfree Notify: Quote submitted for order creation with quoteID:$quoteId failed with error: ". $e->getMessage());
            return;
        }

    }
    
    /**
     * Get quote object
     *
     * @param  mixed $params
     * @param  mixed $quoteId
     * @return void
     */
    protected function getQuoteObject($params, $quoteId)
    {
        $quote = $this->quoteRepository->get($quoteId);

        $firstName = $quote->getBillingAddress()->getFirstname() ?? 'null';
        $lastName  = $quote->getBillingAddress()->getLastname() ?? 'null';
        $email     = $quote->getBillingAddress()->getEmail() ?? 'null';

        $quote->getPayment()->setMethod(PaymentMethod::METHOD_CODE);

        $store = $quote->getStore();

        if(empty($store) === true)
        {
            $store = $this->storeManagement->getStore();
        }

        $websiteId = $store->getWebsiteId();

        $customer = $this->objectManagement->create('Magento\Customer\Model\Customer');
        
        $customer->setWebsiteId($websiteId);

        //get customer from quote , otherwise from payment email
        $customer = $customer->loadByEmail($email);
        
        //if quote billing address doesn't contains address, set it as customer default billing address
        if ((empty($quote->getBillingAddress()->getFirstname()) === true) and
            (empty($customer->getEntityId()) === false))
        {   
            $quote->getBillingAddress()->setCustomerAddressId($customer->getDefaultBillingAddress()['id']);
        }

        //If need to insert new customer as guest
        if ((empty($customer->getEntityId()) === true) or
            (empty($quote->getBillingAddress()->getCustomerId()) === true))
        {
            $quote->setCustomerFirstname($firstName);
            $quote->setCustomerLastname($lastName);
            $quote->setCustomerEmail($email);
            $quote->setCustomerIsGuest(true);
        }

        $quote->setStore($store);

        $quote->collectTotals();

        $quote->save();

        return $quote;
    }

}
