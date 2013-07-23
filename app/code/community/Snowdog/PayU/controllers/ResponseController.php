<?php
/**
 *
 * PayU main controller. Receives server responses
 * @author Snowdog
 *
 */
class Snowdog_PayU_ResponseController extends Mage_Core_Controller_Front_Action {


  /**
   *
   * PositiveUrl, just to show an information to customer about success. Also log the entry
   */
  public function successAction() {
    Mage::helper('snowpayu/log')->log('== successRespAction >>>');
    
    if ($order_id = $this->getRequest()->getParam('session_id')) {
      Mage::helper('snowpayu/log')->log($order_id);
    }
    Mage::helper('snowpayu/log')->log('<<<<s successRespAction');
    $this->_redirect('checkout/onepage/success');
  }

  /**
   *
   * NegativeUrl, just to show an information to customer about failure. Also log the entry
   */
  public function failureAction() {
    Mage::helper('snowpayu/log')->log('== failureRespAction >>>');
    if ($order_id = $this->getRequest()->getParam('session_id')) {
      Mage::helper('snowpayu/log')->log($order_id);
    }
    Mage::helper('snowpayu/log')->log('<<<<f failureRespAction');
    $this->_redirect('payu/notification/failure');
  }

  /**
   *
   * And last, but not least - most importaint OnlineUrl providing
   * information about order status change
   */
  public function reportAction() {
    $report = Mage::getModel('snowpayu/report');
    $report->processRequest($this->getRequest());
    //always, no matter if request is valid or not
    $this->confirm($this->getResponse());
  }

  /**
   *
   * Say 'OK' to your PayU friend. Its required by the server.
   * PayU will continue to send reports, until it will get the "OK" confirmation.
   */
  protected function confirm($response) {
    $response->setBody($this->getLayout()->createBlock('snowpayu/report')->toHtml());
  }
  
  // end class
}
