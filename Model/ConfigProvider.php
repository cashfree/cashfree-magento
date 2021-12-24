<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Payment\Helper\Data as PaymentHelper;
use Cashfree\Cfcheckout\Model\PaymentMethod;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = PaymentMethod::METHOD_CODE;

    /**
     * @var \Cashfree\Cfcheckout\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Url Builder
     *
     * @var \Magento\Framework\Url
     */
    protected $urlBuilder;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Url $urlBuilder
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Url $urlBuilder,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\View\Asset\Repository $assetRepo
    ) {
        $this->config = $config;
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->config->isActive()) {
            return [];
        }

        $config = [
            'payment' => [
                'cashfree' => [
                    'app_id'    => $this->config->getAppId(),
                    'title'     => $this->config->getTitle()
                ],
            ],
        ];

        return $config;
    }
}