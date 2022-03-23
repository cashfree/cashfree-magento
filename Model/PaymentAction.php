<?php 

namespace Cashfree\Cfcheckout\Model;

use \Magento\Framework\Option\ArrayInterface;

class PaymentAction implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => \Cashfree\Cfcheckout\Model\PaymentMethod::ACTION_AUTHORIZE,
                'label' => __('Authorize Only'),
            ],
            [
                'value' => \Cashfree\Cfcheckout\Model\PaymentMethod::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Authorize and Capture')
            ]
        ];
    }
}