<?php

/**
 * IndexController
 *
 * @author mamut
 */
class Snowdog_PayU_NotificationController extends Mage_Core_Controller_Front_Action {
	  /**
   *
   * Displayed on failure response from PayU
   */
  public function failureAction () {
    $session = Mage::getSingleton('checkout/session');
    $this->loadLayout();
    $this->_initLayoutMessages('snowpayu/session');    
    $this->renderLayout();
  }
  
    /**
   *
   * Redirect customer to PayU
   */
  public function redirectAction() {
    $session = Mage::getSingleton('checkout/session');
    $session->setPayUStandardQuoteId($session->getQuoteId());
    $this->getResponse()->setBody($this->getLayout()->createBlock('snowpayu/redirect')->toHtml());
    $session->unsQuoteId();
  }
}

