<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Cron\Exception;
use Magento\Framework\Model\AbstractModel;

class OrderLink extends AbstractModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Cashfree\Cfcheckout\Model\ResourceModel\OrderLink::class);
    }
    
}
