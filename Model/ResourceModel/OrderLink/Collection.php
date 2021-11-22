<?php
namespace Cashfree\Cfcheckout\Model\ResourceModel\OrderLink;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;


class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('Cashfree\Cfcheckout\Model\OrderLink', 'Cashfree\Cfcheckout\Model\ResourceModel\OrderLink');
    }
}