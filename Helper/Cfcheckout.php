<?php

namespace Cashfree\Cfcheckout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Helper\AbstractHelper;

class Cfcheckout extends AbstractHelper
{
    protected $quote;
    protected $session;
    protected $quoteManagement;
    
    /**
     * Initialise helper function for checkout
     *
     * @return void
     */
    public function __construct(
        Context $context,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Checkout\Model\Session $session,
        \Magento\Quote\Model\QuoteManagement $quoteManagement
    ) {
        $this->session          = $session;
        $this->quote            = $quote;
        $this->quoteManagement  = $quoteManagement;
        parent::__construct($context);
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
