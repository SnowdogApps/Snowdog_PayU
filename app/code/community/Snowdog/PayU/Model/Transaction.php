<?php
/**
 *
 * Transaction handling class. Gathers the data from PayU service.
 * @see Snowdog_PayU_Model_Transaction::processTransaction fills the model with data
 * @author Snowdog
 *
 */
class Snowdog_PayU_Model_Transaction extends Mage_Core_Model_Session_Abstract {

  /**
   *
   * PayU status response
   * @var SimpleXMLElement
   */
  protected $response;

  /**
   *
   * Magento order id
   */
  protected $session_id;

  /**
   *
   * Shop id
   */
  protected $pos_id;

  /**
   *
   * @var Snowdog_PayU_Helper_Log
   */
  protected $logHelper;

  /**
   *
   * @var Snowdog_PayU_Model_Config
   */
  protected $config;

  public function __construct() {
    $this->logHelper = Mage::helper('snowpayu/log');
    $this->config = Mage::getSingleton('snowpayu/config');
  }

  public function getOrderId() {
    return $this->session_id;
  }

  /**
   * Returns the transaction data obtained in getTransactionStatus.
   * @return SimpleXMLElement
   */
  public function getTransactionInfo() {
    if ($this->response == null) {
      $this->logHelper->log('Order id: '.$this->session_id);
      $this->logHelper->log('Missing/incomplete transaction data received from server');
      return null;
    }
    return $this->response->trans;
  }

  /**
   *
   * Gets the status data from PayU, checks the signatures.
   * @param $pos_id Shop id
   * @param $session_id Magento Order id
   * @return true on success, false on failure
   */
  public function processTransaction($pos_id, $session_id) {
    $this->pos_id = $pos_id;
    $this->session_id = $session_id;

    //Request data from the server
    $this->getTransactionStatus();
    if ($this->request!==false) {
      if ($this->checkTransactionSignature()) {
        $this->logTransaction();
        $this->logHelper->log('s:status '.$this->getTransactionInfo()->status);
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  /**
   *
   * Checks signature of response from Payment/get
   * procedure
   */
  protected function checkTransactionSignature() {
    if ($this->response === false) {
      return false;
    }

    $signature = md5(
      (string) $this->response->trans->pos_id.
      (string) $this->response->trans->session_id.
      (string) $this->response->trans->order_id.
      (string) $this->response->trans->status.
      (string) $this->response->trans->amount.
      (string) $this->response->trans->desc.
      (string) $this->response->trans->ts.
      $this->config->getSecondMd5()
    );

    if ($signature  != (string) @$this->getTransactionInfo()->sig) {
      $this->logHelper->log((string)$this->response->trans->pos_id);
      $this->logHelper->log('r:wrong response signature');
      return false;
    }
    if ($this->session_id != (string) @$this->getTransactionInfo()->session_id) {
      $this->logHelper->log('r:wrong session_id');
      return false;
    }

    return true;
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
   * Get and update information about transaction
   * from PayU using Payment/get.
   */
  protected function getTransactionStatus() {
    $params = $this->getTransactionStatusParams();
    
    $request_options = array(
      CURLOPT_URL      => $this->config->getReportingURI(),
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_HEADER     => 0,
      CURLOPT_TIMEOUT    => 20,
      CURLOPT_POST       => 1,
      CURLOPT_POSTFIELDS   => implode("&", $params),
      CURLOPT_RETURNTRANSFER => 1,
    );

    $request = curl_init();
    foreach($request_options as $option => $value) {
      curl_setopt($request, $option, $value);
    }

    $xmlResponse = $this->encodeToXml(curl_exec($request));

    curl_close($request);
    $this->response = $xmlResponse;
  }

  /**
   *
   * Provide POST params for Payment/get procedure
   * @return array
   */
  protected function getTransactionStatusParams() {
    $timestamp = date('U');
    $key1 = $this->config->getFirstMd5();

    $hash = md5(
      $this->pos_id
      . $this->session_id
      . $timestamp
      . $key1
    );

    $params = array(
			'pos_id' => $this->pos_id,
			'session_id' => $this->session_id,
			'ts' => $timestamp,
			'sig' => $hash
    );

    foreach ($params as $key => $value) {
      $params[$key] = $key . '=' . $value;
    }

    return $params;
  }

  /**
   *
   * Write a transaction log to Database
   */
  protected function logTransaction() {
    $reader = Mage::getResourceModel('snowpayu/payu');
    $reader->logTransaction($this->getTransactionInfo());
  }

  /**
   *
   * Get the latest transaction log
   * @return array
   */
  public function getLastLog() {
    $reader = Mage::getResourceModel('snowpayu/payu');
    return $reader->getLastLogForTransaction($this->session_id);
  }

  /**
   *
   * Check if status is already in database. PayU tends to send double reports
   * on 99 and 2 status codes. 
   * @param $status status code
   * @return true|false
   */
  public function checkIfStatusAlreadyReceived($status) {
    $reader = Mage::getResourceModel('snowpayu/payu');
    return $reader->checkIfStatusAlreadyInDb($this->session_id, $status);
  }
  
  // end class
}
