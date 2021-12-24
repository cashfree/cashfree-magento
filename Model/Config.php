<?php

namespace Cashfree\Cfcheckout\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;

class Config
{
    const KEY_ALLOW_SPECIFIC = 'allowspecific';
    const KEY_SPECIFIC_COUNTRY = 'specificcountry';
    const KEY_ACTIVE = 'active';
    const KEY_PUBLIC_KEY = 'app_id';
    const KEY_TITLE = 'title';
    const PAYMENT_ENVIRONMENT = 'environment';
    const NOTIFY_WAIT_TIME = 'notify_wait_time';

    /**
     * @var string
     */
    protected $methodCode = 'cashfree';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

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
        WriterInterface $configWriter
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
    }

    public function getAppId()
    {
        return $this->getConfigData(self::KEY_PUBLIC_KEY);
    }

    public function getTitle()
    {
        return $this->getConfigData(self::KEY_TITLE);
    }
    
    public function getPaymentEnvironment()
    {
        return $this->getConfigData(self::PAYMENT_ENVIRONMENT);
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }

        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Set information from payment configuration
     *
     * @param string $field
     * @param string $value
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function setConfigData($field, $value)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;

        return $this->configWriter->save($path, $value);
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     */
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
