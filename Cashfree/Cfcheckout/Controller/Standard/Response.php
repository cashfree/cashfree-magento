<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

class Response extends \Cashfree\Cfcheckout\Controller\CfAbstract {

    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        try {
            $paymentMethod = $this->getPaymentMethod();
            $params = $this->getRequest()->getParams();
            $status = $paymentMethod->validateResponse($params);
            $order = $this->getOrder();
            $orderStatus = $order->getStatus();
            if($orderStatus=="pending" or $orderStatus=="processing"){ 
                if ($status == "SUCCESS") {
                    $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                    $payment = $order->getPayment();
                    $paymentMethod->postProcessing($order, $payment, $params);
                    $this->messageManager->addSuccess(__('Your payment was successful'));

                } else if ($status == "CANCELLED") {
                    $this->_checkoutHelper->cancelCurrentOrder($params['txMsg']);
                    $this->_checkoutSession->restoreQuote();
                    $this->messageManager->addError($params['txMsg']);
                    return $this->_redirect('checkout/cart');
                    
                } else if ($status == "FAILED") {
                    $this->_checkoutHelper->cancelCurrentOrder($params['txMsg']);
                    $this->_checkoutSession->restoreQuote();
                    $this->messageManager->addError($params['txMsg']);
                    return $this->_redirect('checkout/cart');
                } else if($status == "PENDING"){
                    
                    $this->messageManager->addWarning(__('Your payment is pending'));

                } else{
                    $this->messageManager->addErrorMessage(__('There is an error.Payment status is pending'));
                    $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/failure');
                }
            } else {

                $this->messageManager->addNotice(__('Your payment was already processed'));
            }    
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

        $this->getResponse()->setRedirect($returnUrl);
    }

}
