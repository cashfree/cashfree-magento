<?php

namespace Cashfree\Cfcheckout\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;

class Config
{
    const KEY_ALLOW_SPECIFIC    = 'allowspecific';
    const KEY_SPECIFIC_COUNTRY  = 'specificcountry';
    const KEY_ACTIVE            = 'active';
    const KEY_PUBLIC_KEY        = 'app_id';
    const KEY_TITLE             = 'title';
    const PAYMENT_ENVIRONMENT   = 'environment';
    const KEY_NEW_ORDER_STATUS  = 'order_status';
    const KEY_ENABLE_INVOICE    = 'enable_invoice';

    /**
     * @var string
     */
    protected $methodCode = 'cashfree';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var int
     */
    protected $storeId = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $helper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->helper           = $helper;
    }

    public function getAppId()
    {
        return $this->getConfigData(self::KEY_PUBLIC_KEY);
    }

    public function getTitle()
    {
        return $this->getConfigData(self::KEY_TITLE);
    }

    public function getNewOrderStatus()
    {
        return $this->getConfigData(self::KEY_NEW_ORDER_STATUS);
    }

    public function getNotifyUrl() {
        return $this->helper->getUrl($this->getConfigData('notify_url'),array('_secure'=>true));
    }

    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }

        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function setConfigData($field, $value)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;

        return $this->configWriter->save($path, $value);
    }

    /**
     * @return bool
     */
    public function canSendInvoice()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ENABLE_INVOICE, $this->storeId);
    }

    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData(self::KEY_ALLOW_SPECIFIC) == 1) {
            $availableCountries = explode(',', $this->getConfigData(self::KEY_SPECIFIC_COUNTRY));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        return true;
    }
}
