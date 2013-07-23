<?php

/**
 * PayU
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Payments
 * @package    PayU
 * @author	   Snowdog
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 *
 * Configuration handling class. Extracts the proper values from the global config
 * @author Snowdog
 *
 */
class Snowdog_PayU_Model_Config extends Varien_Object {

  /**
   *
   * Shop Id in PayU database
   */
  public function getPosId() {
    return $this->getConfigData("pos_id");
  }

  /**
   * 
   * PayU authorization key
   */
  public function getPosAuthKey() {
    return $this->getConfigData("pos_auth_key");
  }

  /**
   * 
   * Hash key used for signing our own requests
   */
  public function getFirstMd5() {
    return $this->getConfigData("firstmd5_url");
  }

  /**
   * 
   * Hash key used in PayU request/response verification
   */
  public function getSecondMd5() {
    return $this->getConfigData("secondmd5_url");
  }

  /**
   * 
   * Paygate URL 
   */
  public function getCgiUrl() {
    return $this->getConfigData("cgi_url");
  }

  /**
   * 
   * URL for creation of new payment (UTF)
   */
  public function getPaymentURI() {
    return $this->getCgiUrl() . "UTF/NewPayment";
  }

  /**
   * 
   * List of allowed (in the backend) payment types
   */
  public function getEnabledPaymentTypes() {
    return $this->getConfigData("allowpaymenttypes");
  }
  
  /**
   * 
   * List of enabled payments (on our PayU account) 
   */
  public function getEnabledPaymentTypesURI() {
    $uri  = $this->getCgiUrl() . "ISO/xml/";
    $uri .= $this->getPosId() . "/";
    $uri .= substr($this->getFirstMd5(), 0, 2) . "/";
    $uri .= "paytype.xml";

    return $uri;
  }

  /**
   * 
   * Payment status URL (for asking the PayU server) 
   */
  public function getReportingURI() {
    $uri  = $this->getCgiUrl() . "ISO/Payment/get";
    return $uri;
  }

  /**
   * 
   * Translates error codes into error messages 
   * @param int $code
   */
  public function getErrorMessagesWithCode($code) {
    $error['205'] = Mage::helper('snowpayu')->__("Transaction amount is lower than minimum amount.");
    $error['206'] = Mage::helper('snowpayu')->__("Transaction amount is higher than maximum amount.");
    $error['207'] = Mage::helper('snowpayu')->__("You have reached transactions limit at this time.");
    $error['501'] = Mage::helper('snowpayu')->__("Authorization failed for this transaction.");
    $error['502'] = Mage::helper('snowpayu')->__("Transaction was started before.");
    $error['503'] = Mage::helper('snowpayu')->__("Transaction is already authorized.");
    $error['504'] = Mage::helper('snowpayu')->__("Transaction was cancelled before.");
    $error['505'] = Mage::helper('snowpayu')->__("Transaction authorization request was sent before.");

    if (array_key_exists($code, $error)) {
      return $error[$code];
    } else {
      return Mage::helper('snowpayu')->__("Transaction Error Occurred, please contact Customer Service.");
    }
  }

  /**
   * 
   * Provides status info based on status code
   * @param int $code
   */
  public function getStatusesWithCode($code) {
    $status['1']   = Mage::helper('snowpayu')->__("New");
    $status['2']   = Mage::helper('snowpayu')->__("Cancelled");
    $status['3']   = Mage::helper('snowpayu')->__("Rejected");
    $status['4']   = Mage::helper('snowpayu')->__("Started");
    $status['5']   = Mage::helper('snowpayu')->__("Waiting For Payment");
    $status['7']   = Mage::helper('snowpayu')->__("Rejected");
    $status['99']  = Mage::helper('snowpayu')->__("Payment Received");
    $status['888'] = Mage::helper('snowpayu')->__("Wrong Status");

    if (array_key_exists($code, $status)) {
      return $status[$code];
    } else {
      return Mage::helper('snowpayu')->__("Wrong Status");
    }
  }
  
  /**
   * 
   * Gets the log file name for PayU. If not specified - "snowpayu.log"
   */
  public function getLogFileName() {
    return $this->getConfigData("log_file_name", "snowpayu.log");
  }

  /**
   * 
   * Get the global config value for a specified key  
   * @param $key
   * @param $default value if key not exists
   */
  public function getConfigData($key, $default = false) {
    if ( ! $this->hasData($key)) {
      $value = Mage::getStoreConfig('payment/snowpayu/' . $key);
      if (is_null($value) || false === $value) {
        $value = $default;
      }
      $this->setData($key, $value);
    }
    return $this->getData($key);
  }
  
  // end class
}