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

    protected $logger;
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
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
        if(isset($forceOrderMailSentOnSuccess))
        {
            // Send order confirmation email after payment completed successfully
            $returnValue = $forceOrderMailSentOnSuccess;
        }
        
        return $returnValue;
    }
}