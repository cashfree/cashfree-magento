<?php
class Cashfree_Cfcheckout_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'cfcheckout';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('cfcheckout/payment/redirect', array('_secure' => true));
	}
}
?>
