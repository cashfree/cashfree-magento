<?php

namespace Cashfree\Cfcheckout\Model;

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
     * @param \Cashfree\Cfcheckout\Model\Config
     */
    public function __construct(
        \Cashfree\Cfcheckout\Model\Config $config
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
                    'in_context'    => $this->config->getInContext(),
                    'title'         => $this->config->getTitle()
                ],
            ],
        ];

        return $config;
    }
}