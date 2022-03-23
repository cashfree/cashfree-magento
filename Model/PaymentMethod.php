<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Payment\Model\InfoInterface;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod {

    const METHOD_CODE       = 'cashfree';
    const ACTION_PROCESSING = 'processing';

    protected $_code = self::METHOD_CODE;

    /**
     * @var bool
     */
    protected $_canAuthorize            = true;

    /**
     * @var bool
     */
    protected $_canCapture              = true;

    /**
     * @var bool
     */
    protected $_canUseInternal          = true;        //Disable module for Magento Admin Order

    /**
     * @var bool
     */
    protected $_canUseCheckout          = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var array|null
     */
    protected $requestMaskedFields      = null;

     /**
     * @var \Cashfree\Cfcheckout\Model\Config
     */
    protected $config;

    /**
     * 
     * @param array $data
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\Context $context
     * @param \Cashfree\Cfcheckout\Model\Config $config
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */

      public function __construct(
        \Magento\Framework\Registry $registry,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\Model\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->config   = $config;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function capture(InfoInterface $payment, $amount)
    {
       return $this;
    }

}
