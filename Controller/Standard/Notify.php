<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

class Notify extends \Cashfree\Cfcheckout\Controller\CfAbstract {

    public function execute() {
        sleep(20);
        try {
            $paymentMethod = $this->getPaymentMethod();
            $params = $this->getRequest()->getParams();
            $status = $paymentMethod->validateResponse($params);
            $order = $this->getOrderById($params['orderId']);
            $orderStatus = $order->getStatus();
            if($orderStatus=="pending"){ 
                if ($status == "SUCCESS") {
                    $payment = $order->getPayment();
                    $paymentMethod->postProcessing($order, $payment, $params);

                } else if ($status == "CANCELLED" || $status == "FAILED" ) {
                     $order->cancel()->save();
             
                } else {
                    //do nothing
                }
            }    
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

    }

}
