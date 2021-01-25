<?php

namespace Cashfree\Cfcheckout\Model;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    protected $methodCode = \Cashfree\Cfcheckout\Model\Cfcheckout::PAYMENT_CFCHECKOUT_CODE;
    
    
    protected $method;
	

    public function __construct(\Magento\Payment\Helper\Data $paymenthelper){
        $this->method = $paymenthelper->getMethodInstance($this->methodCode);
    }
    
    /**
     * Get configuration
     *
     * @return void
     */
    public function getConfig(){

        return $this->method->isAvailable() ? [
            'payment'=>['cfcheckout'=>[
                'redirectUrl'=>$this->method->getRedirectUrl()  
            ]
        ]
        ]:[];
    }
}
