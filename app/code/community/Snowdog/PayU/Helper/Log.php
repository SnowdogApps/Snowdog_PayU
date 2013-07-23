<?php
/**
 *
 * Log helper
 * @author Snowdog
 */
class Snowdog_PayU_Helper_Log extends Mage_Core_Helper_Abstract {

  /**
   * 
   * Log in the proper log file (set in global config)
   * @param mixed $logContent
   */
  public function log($logContent) {
    Mage::log($logContent, null, Mage::getSingleton('snowpayu/config')->getLogFileName());
  }

  public function beginReport() {
    $this->log('== reportRespAction >>>');
  }

  public function endReport() {
    $this->log('<<<<r reportRespAction');
  }
  
  /**
   * 
   * Log the whole server response 
   * @param $zendRequest
   */
  public function serverResponse($zendRequest) {
    $order_id = $this->getRequest()->getPost('session_id');
    $pos_id = $this->getRequest()->getPost('pos_id');
    $ts = $this->getRequest()->getPost('ts');
    $sig = $this->getRequest()->getPost('sig');

    $this->log('r:param::order_id = ' . $order_id);
    $this->log('r:param::pos_id = ' . $pos_id);
    $this->log('r:param::ts = ' . $ts);
    $this->log('r:param::sig = ' . $sig);
  }
  // end class
}