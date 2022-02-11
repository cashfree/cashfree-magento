<?php
namespace Cashfree\Cfcheckout\Plugin\Sales\Order\Email\Container;

class OrderIdentityPlugin
{
    /**
     * @var \Magento\Checkout\Model\Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param \Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject
     * @param callable $proceed
     * @return bool
     */
    public function aroundIsEnabled(\Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject, callable $proceed)
    {
        $returnValue = $proceed();
        
        $forceOrderMailSentOnSuccess = $this->checkoutSession->getCashfreeMailSentOnSuccess();
        
        if($forceOrderMailSentOnSuccess === true)
        {
            $returnValue = $forceOrderMailSentOnSuccess;
            $this->checkoutSession->unsCashfreeMailSentOnSuccess();       
        } else {
            $returnValue = $this->checkoutSession->getCashfreeMailSentOnSuccess();
        }
        
        return $returnValue;
    }
}