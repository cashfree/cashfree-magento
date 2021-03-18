<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Cashfree\Cfcheckout\Model\Cfcheckout;
use Magento\Framework\Controller\ResultFactory;

class Response extends \Cashfree\Cfcheckout\Controller\CfAbstract {

    protected $quote;

    protected $checkoutSession;

    protected $customerSession;

    protected $cache;

    protected $orderRepository;

    protected $invoiceService;
    
    /**
     * __construct
     *
     * @return void
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
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepo,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->logger           = $logger;
        $this->_customerSession = $customerSession;
        $this->quoteRepository  = $quoteRepository;
        $this->_paymentMethod   = $paymentMethod;
        $this->quoteManagement  = $quoteManagement;
        $this->storeManagement  = $storeManagement;
        $this->customerRepo     = $customerRepo;
        $this->customerFactory  = $customerFactory;
        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
        
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
    }
    
    /**
     * execute
     *
     * @return void
     */
    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');
        $params = $this->getRequest()->getParams();
        $quoteId   = $params['orderId'];
        $quote = $this->getQuoteObject($params, $quoteId);
        if (!$this->getCustomerSession()->isLoggedIn()) {
            $customerId = $quote->getCustomer()->getId();
            if(!empty($customerId)) {
                $customer = $this->customerFactory->create()->load($customerId);
                $this->_customerSession->setCustomerAsLoggedIn($customer);
            }
        }
        try {
            $paymentMethod = $this->getPaymentMethod();
            $status = $paymentMethod->validateResponse($params);
            $debugLog = "";
            if ($status == "SUCCESS") {
                $order = $this->quoteManagement->submit($quote);

                $payment = $order->getPayment();        
                
                $paymentMethod->postProcessing($order, $payment, $params);
                $this->_checkoutSession
                            ->setLastQuoteId($quote->getId())
                            ->setLastSuccessQuoteId($quote->getId())
                            ->clearHelperData();
                
                $this->_checkoutSession->setLastOrderId($order->getId())
                                           ->setLastRealOrderId($order->getIncrementId())
                                           ->setLastOrderStatus($order->getStatus());
              
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                $this->messageManager->addSuccess(__('Your payment was successful'));
                $debugLog = "Order status changes to processing for quote id: ".$quoteId;

            } else if ($status == "CANCELLED") {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->_checkoutSession->replaceQuote($quote);
                $this->messageManager->addError($params['txMsg']);
                $debugLog = "Order status changes to cancelled for quote id: ".$quoteId;
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/cart');
                
            } else if ($status == "FAILED") {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->_checkoutSession->replaceQuote($quote);
                $this->messageManager->addError($params['txMsg']);
                $debugLog = "Order status changes to falied for quote id: ".$quoteId;
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/cart');

            } else if($status == "PENDING"){
                $debugLog = "Order status changes to pending for quote id: ".$quoteId;
                $this->messageManager->addWarning(__('Your payment is pending'));

            } else{
                $debugLog = "Order status changes to pending for quote id: ".$quoteId;
                $this->messageManager->addErrorMessage(__('There is an error.Payment status is pending'));
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/failure');
            }

            $enabledDebug = $paymentMethod->enabledDebugLog();
            if($enabledDebug === "1"){
                $this->logger->info($debugLog);
            }
              
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

        $this->getResponse()->setRedirect($returnUrl);
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