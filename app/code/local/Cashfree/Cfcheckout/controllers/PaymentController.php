<?php

class Cashfree_Cfcheckout_PaymentController extends Mage_Core_Controller_Front_Action {
	// The redirect action is triggered when someone places an order
	public function redirectAction() {
    //getting request here
         
    // Retrieve order
    $_order = new Mage_Sales_Model_Order();
    $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
    $_order->loadByIncrementId($orderId);

    if (!$_order->getId()) {
      Mage::throwException('No order found');
    }

    if ($_order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
      $_order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
    }

    $address = $_order->getShippingAddress();

    $appId = Mage::getStoreConfig('payment/cfcheckout/merchant_app_id');
    $secretKey = Mage::getStoreConfig('payment/cfcheckout/merchant_secret_key');

    $endPoint = Mage::getStoreConfig('payment/cfcheckout/gateway_end_point');
    $endPoint = rtrim($endPoint,"/");
    $redirectUrl = $endPoint."/checkout/post/submit";

    $customerName = $_order->getCustomerFirstname()." ".$_order->getCustomerLastname();
    $returnUrl = Mage::getUrl('cfcheckout/payment/response', array('_secure' => true));
    $notifyUrl = Mage::getUrl('cfcheckout/payment/notify', array('_secure' => true));
    $returnUrl = Mage::getModel('core/url')->sessionUrlVar($returnUrl);
    $notifyUrl = Mage::getModel('core/url')->sessionUrlVar($notifyUrl);

    $postData = array();
    $postData["orderId"] = $orderId;
    $postData["orderAmount"] = round($_order->getBaseGrandTotal(), 2);
    $postData["customerPhone"] = (string)$address->getTelephone();
    $postData["customerEmail"] = (string)$_order->getCustomerEmail();
    $postData["customerName"] = $customerName;
    $postData["appId"] = $appId;
    $postData["returnUrl"] =  $returnUrl;
    $postData["notifyUrl"] =  $notifyUrl;
    $postData["source"] = "magento";

    ksort($postData);

    $signData = "";
    foreach ($postData as $key => $value){
      $signData .= $key.$value;
    }

    $signature = hash_hmac('sha256', $signData, $secretKey, true);
    $signature = base64_encode($signature);
    $postData["signature"] = $signature;

    Mage::register('post_data', $postData);
    Mage::register('redirect_url', $redirectUrl);
    $block = $this->getLayout()->createBlock('core/template');
    $block->setTemplate('cfcheckout/redirect.phtml');
	  echo $block->toHtml();
  }
	
  // The response action is triggered when your gateway sends back a response after processing the customer's payment
  public function notifyAction() {
    if($this->getRequest()->isPost()) {
      $this->processCFResponse("notify");
    }
  }
       
	// The response action is triggered when your gateway sends back a response after processing the customer's payment
	public function responseAction() {
	  if($this->getRequest()->isPost()) {
	    $result = $this->processCFResponse();
      if ($result->status) {				
          Mage::getSingleton('checkout/session')->unsQuoteId();
          Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
      } else {
        switch ($result->reason) {
          case "failed":
            $this->cancelOrder($result->orderId);
            Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__($result->error));
            Mage_Core_Controller_Varien_Action::_redirect('checkout/cart', array('_secure'=>true));
            break;
          case "flagged":
            Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__($result->error));
            Mage_Core_Controller_Varien_Action::_redirect('checkout/cart', array('_secure'=>true));
            break;
          default:
            $this->cancelAction();
            Mage_Core_Controller_Varien_Action::_redirect('');
        }  
      }
	  } else {
      Mage_Core_Controller_Varien_Action::_redirect('');
	  }
  }

  public function processCFResponse($requestType = "display") {
    $response = new stdClass(); 
    $orderId = $this->getRequest()->getPost('orderId');	
    $orderAmount = $this->getRequest()->getPost('orderAmount');	
    $paymentMode = $this->getRequest()->getPost('paymentMode');	
    $referenceId = $this->getRequest()->getPost('referenceId');	
    $txStatus = $this->getRequest()->getPost('txStatus');	
    $txTime = $this->getRequest()->getPost('txTime');	
    $txMsg = $this->getRequest()->getPost('txMsg');
    $signature = $this->getRequest()->getPost('signature');
                
    $secretKey = Mage::getStoreConfig('payment/cfcheckout/merchant_secret_key'); 
    $data = $orderId.$orderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;
    $hash_hmac = hash_hmac('sha256', $data, $secretKey, true) ;
    $computedSignature = base64_encode($hash_hmac); 
    
    if ($computedSignature === $signature) {
      //good to go
    } else {
      $response->status = 0;
      $response->reason = "invalid";
      return $response;
    }

    if ($txStatus == "SUCCESS") {
      // Payment was successful, so update the order's state, send order email and move to the success page
      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($orderId);

      if ($order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE ||
                $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {
        $response->status = 1;
        $response->reason = "duplicate";
        return $response;
      }

      $orderStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
      $orderState = Mage_Sales_Model_Order::STATE_PROCESSING;
      $order->setState($orderState, $orderStatus ? $orderStatus : true, $txMsg);           
        
      $order->sendNewOrderEmail();
      $order->setEmailSent(true);
      $this->createInvoice($order); 

      $order->save();
      $response->status = 1;
      return $response;
        //redirect
    } else {
      switch ($txStatus) {
        case "CANCELLED":
          $response->reason = "failed";
          $error = "Your payment has been cancelled";                 
          break;
        case "FLAGGED":
          $response->reason = "flagged";
          $error = "Your payment is complete and under review.";                 
          break;
        case "PENDING":
          $response->reason = "pending";
          $error = "We are waiting for status update.";                 
          break;
        default:
            $response->reason = "failed";
            $error = "Your payment has failed. Please try again."; 
      }
      $response->status = 0;
      $response->error = $error;
      $response->orderId = $orderId;
      return $response;
    }
  }
	
	// The cancel action is triggered when an order is to be cancelled
	public function cancelAction() {
    if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
      $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
      if($order->getId()) {
        // Flag the order as 'cancelled' and save it
        $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
      }
    }
    $error = "Your payment has been cancelled";
    Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__($error));
  }

  public function cancelOrder($orderId) {
    $cart = Mage::getSingleton('checkout/cart');
    $order = Mage::getModel('sales/order');
    $order->loadByIncrementId($orderId);
    $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment')->save();
    $items = $order->getItemsCollection();
    foreach ($items as $item) {
      try {
        $cart->addOrderItem($item);
      } catch (Mage_Core_Exception $e) {
        Mage::getSingleton('checkout/session')->addError($this->__($e->getMessage()));
        Mage::logException($e);
        continue;
      }
    }
    $cart->save();
  }

  public function createInvoice($order) {
    try {
      if(!$order->canInvoice()) {
        Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
      } 

      $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
      
      if (!$invoice->getTotalQty()) {
        Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
      }

      $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
      $invoice->register();
      $invoice->getOrder()->setIsInProcess(true);
      $history = $invoice->getOrder()->addStatusHistoryComment(
          'Programmatically created invoice', true
      );
      $transactionSave = Mage::getModel('core/resource_transaction')
                          ->addObject($invoice)
                          ->addObject($order);
      $transactionSave->save();
      $invoice->sendEmail(true, '');
      Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
    } catch (Mage_Core_Exception $e) {}

  }
}