<?php
namespace Cashfree\Cfcheckout\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

abstract class CfAbstract extends Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

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
     * @var \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    protected $resultJsonFactory;
      
    /**
     * __construct
     *
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Cashfree\Cfcheckout\Model\Cfcheckout $paymentMethod,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $checkoutHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutHelper = $checkoutHelper;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Instantiate quote and checkout
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function initCheckout()
    {
        $quote = $this->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t initialize checkout.'));
        }
    }

    /**
     * Cancel order, return quote to customer
     *
     * @param string $errorMsg
     * @return false|string
     */
    protected function _cancelPayment($errorMsg = '')
    {
        $gotoSection = false;
        $this->_checkoutHelper->cancelCurrentOrder($errorMsg);
        if ($this->_checkoutSession->restoreQuote()) {
            //Redirect to payment step
            $gotoSection = 'paymentMethod';
        }

       return $gotoSection;
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrderById($order_id)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->get('Magento\Sales\Model\Order');
        $order_info = $order->load($order_id);
        return $order_info;
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        return $this->_orderFactory->create()->loadByIncrementId(
            $this->_checkoutSession->getLastRealOrderId()
        );
    }
    
    /**
     * Get Quote Object
     *
     * @return void
     */
    protected function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }
    
    /**
     * Get CheckoutSession
     *
     * @return void
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }
    
    /**
     * Get CustomerSession
     *
     * @return void
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }
    
    /**
     * Get PaymentMethod 
     *
     * @return void
     */
    public function getPaymentMethod()
    {
        return $this->_paymentMethod;
    }
    
    /**
     * Get CheckoutHelper
     *
     * @return void
     */
    protected function getCheckoutHelper()
    {
        return $this->_checkoutHelper;
    }
	
}