<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Magento\Framework\DataObject;
use Cashfree\Cfcheckout\Model\Cfcheckout;
use Magento\Framework\App\RequestInterface;
use Cashfree\Cfcheckout\Model\CheckoutFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

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
     * @var \Tco\Checkout\Model\Checkout
     */
    protected $_paymentMethod;

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
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Cashfree\Model\CheckoutFactory $checkoutFactory
     * @param \Magento\Catalog\Model\Session $catalogSession
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository,
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagement
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Psr\Log\LoggerInterface $logger
     */

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Cashfree\Cfcheckout\Model\Cfcheckout $paymentMethod,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $checkoutHelper,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) 
    {
        parent::__construct(
            $cache,
            $order,
            $context,
            $orderFactory,
            $customerSession,
            $checkoutSession,
            $paymentMethod,
            $quoteManagement,
            $checkoutHelper,
            $storeManagement,
            $resultJsonFactory
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
        sleep(20);
        $params = $this->getRequest()->getParams();
        $paymentMethod = $this->getPaymentMethod();
        $status = $paymentMethod->validateResponse($params);
        $referenceId    = $params['referenceId'];
        if ($status == "SUCCESS") {
            $quoteId = strip_tags($params["orderId"]);
            list($quoteId) = explode('_', $quoteId);
            if (empty($this->cache->load("quote_Front_processing_".$quoteId)) === false)
            {
                $this->logger->info("Cashfree Webhook: Order processing is active for quoteID: $quoteId");
                header('Status: 409 Conflict, too early for processing', true, 409);
                exit;
            }

            $this->cache->save("started", "quote_processing_$quoteId", ["cashfree"], 30);

            //validate if the quote Order is still active
            $quote = $this->quoteRepository->get($quoteId);

            $quoteAmount = number_format($quote->getGrandTotal(), 2, '.', '');
            if ($quoteAmount !== $params['orderAmount'])
            {
                $this->logger->info("Cashfree notify: Amount paid doesn't match with store order amount for Cashfree reference_id(:$referenceId)");
                    return;
            }

            //exit if quote is not active
            if (!$quote->getIsActive())
            {
                $this->logger->info("Cashfree Webhook: Quote order is inactive for quoteID: $quoteId");
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
                    $this->logger->info("Cashfree Webhook: Sales Order and payment already exist for Cashfree reference_id(:$referenceId)");
                    return;
                }
            }

            $quote = $this->getQuoteObject($params, $quoteId);

            $order = $this->quoteManagement->submit($quote);

            $payment = $order->getPayment();        
            
            $paymentMethod->postProcessing($order, $payment, $params);
        
            //disable the quote
            $quote->setIsActive(0)->save();

            $this->logger->info("Cashfree Webhook Processed successfully for Cashfree reference_id(:$referenceId): and quoteID(: $quoteId) and OrderID(: ". $order->getEntityId() .")");
        }
        else {
            $this->logger->info("Cashfree Webhook: Check with merchant for Cashfree reference_id(:$referenceId)");
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

        $quote->getPayment()->setMethod(Cfcheckout::PAYMENT_CFCHECKOUT_CODE);

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
