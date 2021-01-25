<?php

namespace Cashfree\Cfcheckout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Helper\AbstractHelper;

class Cfcheckout extends AbstractHelper
{
    protected $session;
    protected $quote;
    protected $quoteManagement;
    protected $orderSender;
    
    /**
     * Initialise helper function for checkout
     *
     * @return void
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement
    ) {
        $this->session = $session;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        parent::__construct($context);
    }
    
    /**
     * Cancel current order
     *
     * @param  mixed $comment
     * @return void
     */
    public function cancelCurrentOrder($comment)
    {
        $order = $this->session->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }
    
    /**
     * Restore quote
     *
     * @return void
     */
    public function restoreQuote()
    {
        return $this->session->restoreQuote();
    }
    
    /**
     * getUrl
     *
     * @param  mixed $route
     * @param  mixed $params
     * @return void
     */
    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }

}
