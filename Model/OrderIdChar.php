<?php
namespace Cashfree\Cfcheckout\Model;

class OrderIdChar implements \Magento\Framework\Option\ArrayInterface
{
    const CHAR_UNDERSCORE = '_';
    const CHAR_HYPEN = '-';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::CHAR_HYPEN,
                'label' => 'Hypen (-)',
            ],[
                'value' => self::CHAR_UNDERSCORE,
                'label' => 'Underscore (_)'
            ]
        ];
    }

}