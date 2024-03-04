<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;

class PaymentMethod extends AbstractMethod {

    const METHOD_CODE       = 'cashfree';

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
     * @var Config
     */
    protected $config;

    /**
     *
     * @param Registry $registry
     * @param Config $config
     * @param Context $context
     * @param Data $paymentData
     * @param Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */

      public function __construct(
          Registry                   $registry,
          Config                     $config,
          Context                    $context,
          Data                       $paymentData,
          Logger                     $logger,
          ScopeConfigInterface       $scopeConfig,
          ExtensionAttributesFactory $extensionFactory,
          AttributeValueFactory      $customAttributeFactory,
          AbstractResource           $resource = null,
          AbstractDb                 $resourceCollection = null,
          array                      $data = []
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

    /**
     * @param InfoInterface $payment
     * @param $amount
     * @return $this|PaymentMethod
     */
    public function capture(InfoInterface $payment, $amount)
    {
       return $this;
    }

}
