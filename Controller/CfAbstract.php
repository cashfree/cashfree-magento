<?php

namespace Cashfree\Cfcheckout\Controller;

use Cashfree\Cfcheckout\Model\Config;
use Magento\Framework\App\RequestInterface;

/**
 * Cashfree Abstract Controller
 */
abstract class CfAbstract extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Cashfree\Cfcheckout\Model\CheckoutFactory
     */
    protected $checkoutFactory;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote = false;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Cashfree\Cfcheckout\Model\PaymentMethod
     */
    protected $checkout;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Cashfree\Cfcheckout\Model\Config $config
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Cashfree\Cfcheckout\Model\Config $config
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
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
     * Return checkout quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        if (!$this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }
}