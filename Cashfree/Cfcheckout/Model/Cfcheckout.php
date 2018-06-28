<?php


namespace Cashfree\Cfcheckout\Model;

use Magento\Sales\Api\Data\TransactionInterface;

class Cfcheckout extends \Magento\Payment\Model\Method\AbstractMethod {

    const PAYMENT_CFCHECKOUT_CODE = 'cfcheckout';
   

    protected $_code = self::PAYMENT_CFCHECKOUT_CODE;

    /**
     *
     * @var \Magento\Framework\UrlInterface 
     */
    protected $_urlBuilder;
    
    private $checkoutSession;

    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
      public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Cashfree\Cfcheckout\Helper\Cfcheckout $helper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Checkout\Model\Session $checkoutSession      
              
    ) {
        $this->helper = $helper;
        $this->orderSender = $orderSender;
        $this->httpClientFactory = $httpClientFactory;
        $this->checkoutSession = $checkoutSession;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );

    }

    /*public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }*/

    public function getRedirectUrl() {
        return $this->helper->getUrl($this->getConfigData('redirect_url'));
    }

    public function getReturnUrl() {
        return $this->helper->getUrl($this->getConfigData('return_url'));
    }

    public function getNotifyUrl() {
        return $this->helper->getUrl($this->getConfigData('notify_url'));
    }

    /**
     * Return url according to environment
     * @return string
     */
    public function getCgiUrl() {
        $env = $this->getConfigData('environment');
        if ($env === 'prod') {
            return $this->getConfigData('prod_url');
        }
        return $this->getConfigData('test_url');
    }

    public function buildCheckoutRequest() {
        $order = $this->checkoutSession->getLastRealOrder();
        $billing_address = $order->getBillingAddress();

        $params = array();
        $params["appId"] = $this->getConfigData("app_id");
        $params["orderId"] = $order->getEntityId();
        $params["orderAmount"] = round($order->getGrandTotal(), 2);
        $params["orderCurrency"] = $order->getOrderCurrencyCode();
        $params["customerName"] = $billing_address->getFirstName(). " ". $billing_address->getLastName();
 
 /*     $params["city"]                 = $billing_address->getCity();
        $params["state"]                = $billing_address->getRegion();
        $params["zip"]                  = $billing_address->getPostcode();
        $params["country"]              = $billing_address->getCountryId();
*/
        $params["customerEmail"] = $order->getCustomerEmail();
        $params["customerPhone"] = $billing_address->getTelephone();
    
        $params["notifyUrl"] = $this->getNotifyUrl();
        
        $params["returnUrl"] = $this->getReturnUrl();
        $params["signature"] = $this->generateCFSignature($params);

        return $params;
    }

    public function generateCFSignature($params) {
        $secretKey = $this->getConfigData('secret_key');
        ksort($params);
        $signatureData = "";
        foreach ($params as $key => $value){
           $signatureData .= $key.$value;
        }
        $signature = hash_hmac('sha256', $signatureData, $secretKey, true);
        return base64_encode($signature);
    }

    //validate response
    public function validateResponse($returnParams) {


          $orderId = $returnParams["orderId"];
          $orderAmount = $returnParams["orderAmount"];   
          $paymentMode = $returnParams["paymentMode"];  
          $referenceId = $returnParams["referenceId"];   
          $txStatus = $returnParams["txStatus"]; 
          $txTime = $returnParams["txTime"]; 
          $txMsg = $returnParams["txMsg"];
          $signature = $returnParams["signature"];
                      
          $secretKey = $this->getConfigData('secret_key');

          $data = $orderId.$orderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;
          $hash_hmac = hash_hmac('sha256', $data, $secretKey, true) ;
          $computedSignature = base64_encode($hash_hmac); 
          
          if ($computedSignature != $signature) {
            return "INVALID";
          }

          return $returnParams["txStatus"];

    }

    public function postProcessing(\Magento\Sales\Model\Order $order,
            \Magento\Framework\DataObject $payment, $response) {
        
        $payment->setTransactionId($response['referenceId']);
        $payment->setTransactionAdditionalInfo('Transaction Message', $response['txMsg']);
        $payment->setAdditionalInformation('cashfree_payment_status', 'approved');
        $payment->addTransaction(TransactionInterface::TYPE_ORDER);
        $payment->setIsTransactionClosed(0);
        $payment->place();
        $order->setStatus('processing');
        $order->save();
    }

}
