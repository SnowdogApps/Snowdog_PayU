<?php
/**
 *
 * Main payment model. Gets the payment types and handles the redirection form.
 * @author Snowdog
 */
class Snowdog_PayU_Model_PayU extends Mage_Payment_Model_Method_Abstract {

  /**
   *	Magento configuration fields
   */
  protected $_code          = 'snowpayu';
  protected $_formBlockType       = 'snowpayu/form';

  protected $_isGateway         = false;
  protected $_canAuthorize      = false;
  protected $_canCapture        = true;
  protected $_canCapturePartial     = false;
  protected $_canRefund         = false;
  protected $_canVoid         = false;
  protected $_canUseInternal      = true;
  protected $_canUseCheckout      = true;
  protected $_canUseForMultishipping  = false;

  protected $_canSaveCc         = false;

  /**
   *
   * @return Snowdog_PayU_Model_Config
   */
  public function getConfig() {
    return Mage::getSingleton('snowpayu/config');
  }

  /**
   *
   * @return Snowdog_PayU_Model_Session
   */
  public function getSession() {
    return Mage::getSingleton('snowpayu/session');
  }

  public function getCheckout() {
    return Mage::getSingleton('checkout/session');
  }

  public function getQuote() {
    return $this->getCheckout()->getQuote();
  }

  /**
   *
   * Encode request as XML
   * @return SimpleXMLElement
   */
  protected function encodeToXml($request) {
    try {
      return new SimpleXMLElement($request);
    } catch(Exception $e) {  //Exception needed for the fresh installation
      return array();
    }
  }


  /**
   *
   * URL for the payu redirection form
   */
  public function getOrderPlaceRedirectUrl() {
    return Mage::getUrl('payu/notification/redirect');
  }

  /**
   *
   * Prepares the data for redirection form.
   * @see Snowdog_PayU_Block_Redirect
   * @return array
   */
  public function getRedirectionFormData() {
    $order_id = $this->getCheckout()->getLastRealOrderId();

    $order  = Mage::getModel('sales/order')->loadByIncrementId($order_id);

    $payment  = $order->getPayment()->getData();
    $billing  = $order->getBillingAddress();
    $redirectionFormData = array(
			"pos_id"     => $this->getConfig()->getPosId(),
			"pay_type"   => $payment['cc_type'],
			"pos_auth_key" => $this->getConfig()->getPosAuthKey(),
			"amount"     => (int)(round($order->getBaseGrandTotal(), 2) * 100),
			"desc"     => Mage::helper('snowpayu')->__("Order no %s", $order_id),
			"city"	     => $billing->getCity(),
			"street"     => implode(" ",$billing->getStreet()),
			"email"	     => $order->getCustomerEmail(),
			"post_code"  => $billing->getPostCode(),	
			"language"   => "pl",
			"first_name"   => $billing->getFirstname(),
			"last_name"  => $billing->getLastname(),
			"client_ip"  => $_SERVER['REMOTE_ADDR'],
			"session_id"   => $order_id,
    );
    return (array)@$redirectionFormData;
  }

  /**
   *
   * Requests the enabled payment types from PayU web service
   * @return array
   */
  public function getEnabledPaymentTypes() {
    $request_options = array(
      CURLOPT_URL      => $this->getConfig()->getEnabledPaymentTypesURI(),
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_HEADER     => 0,
      CURLOPT_TIMEOUT    => 20,
      CURLOPT_RETURNTRANSFER => 1,
    );

    $request = curl_init();

    foreach($request_options as $option => $value) { 
      curl_setopt($request, $option, $value); 
    }

    $response = $this->encodeToXml(curl_exec($request));
    	
    curl_close($request);

    return (array_key_exists("paytype", $response) ? $response->paytype : array());
  }

  /**
   *
   * Checks if payment is enabled
   * @param string $payment
   * @return true|false
   */
  public function checkIfPaymentIsEnabled($payment) {
    $payments_enabled = explode(",", $this->getConfig()->getEnabledPaymentTypes());

    if (in_array($payment, $payments_enabled)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }
  
  // end class
}
