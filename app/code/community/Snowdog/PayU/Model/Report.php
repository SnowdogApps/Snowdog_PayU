<?php
/**
 *
 * PayU report handling class (URLOnline). Changes order state.
 * @author Snowdog
 */
class Snowdog_PayU_Model_Report extends Mage_Core_Model_Abstract{
  /**
   *
   * Request received from PayU
   * @var mixed
   */
  protected $request = null;

  /**
   *
   * PayU shop id received in the request
   * @var int
   */
  protected $pos_id = null;

  /**
   *
   * Order id received in the request
   * @var int
   */
  protected $session_id = null;

  /**
   *
   * Timestamp, used to verify the transaction signature. Received in the platnocipl request
   * @var int
   */
  protected $ts = null;

  /**
   *
   * Transaction signature. Received in the platnocipl request
   * @var string
   */
  protected $sig = null;

  /**
   *
   * Response from the PayU payment information request
   * @var Snowdog_PayU_Model_Transaction
   */
  protected $transaction = null;

  /**
   *
   * Last transaction log in db
   */
  protected $lastLog = null;

  /**
   *
   * Magento order object
   * @var Mage_Sales_Model_Order
   */
  protected $order = null;

  /**
   *
   * @var Snowdog_PayU_Model_Config
   */
  protected $config = null;


  /**
   * Log helper instance
   * @var Snowdog_PayU_Helper_Log
   */
  protected $logHelper;

  //Status messages. Are translated in setOrderStatus.
  public static $MSG_INVALID_RESPONSE = 'Transaction response was invalid.';
  public static $MSG_INVALID_SUM = 'Order total amount does not match PayU gross total amount.';
  public static $MSG_ORDER_STATUS = 'Transaction was returned with status : ';
  public static $MSG_INVALID_PAYMENT = 'Warning: Customer was using disabled payment method.';
  public static $MSG_NO_INVOICE = 'Transaction was successful but system couldn\'t create an Invoice.';

  public static $PAYU_CODE_TO_MAGENTO_STATUS = array(
    1 => Mage_Sales_Model_Order::STATE_NEW, //new
    4 => Mage_Sales_Model_Order::STATE_NEW, //started
    5 => Mage_Sales_Model_Order::STATE_NEW, //waiting
    2 => Mage_Sales_Model_Order::STATE_CANCELED, //canceled
    3 => Mage_Sales_Model_Order::STATE_CANCELED, //refused
    7 => Mage_Sales_Model_Order::STATE_CANCELED, //returned to client
    99 => Mage_Sales_Model_Order::STATE_PROCESSING //ended
  );

  /**
   * 
   * Transaction status, on which we decide that we can ship the order   
   */
  const TRANSACTION_FINISHED = 99;

  public function __construct() {
    $this->logHelper = Mage::helper('snowpayu/log');
    $this->config = Mage::getSingleton('snowpayu/config');
    $this->transaction = Mage::getModel('snowpayu/transaction');
  }

  /**
   *
   * Extract params from the PayU request (save them in the model)
   * and make sure that anything isn't missing.
   * @return true|false
   */
  protected function checkParams() {
    if ( ! ($this->pos_id = $this->request->getParam('pos_id')) ) {
      $this->logHelper->log('<<<s no pos_id');
      return false;
    } else {
      $this->logHelper->log('s:param::pos_id = ' . $this->pos_id);
    }

    if ( ! ($this->session_id = $this->request->getParam('session_id')) ) {
      $this->logHelper->log('<<<s no session_id');
      return false;
    } else {
      $this->logHelper->log('s:param::session_id = ' . $this->session_id);
    }

    if ( ! ($this->ts = $this->request->getParam('ts')) ) {
      $this->logHelper->log('<<<s no ts');
      return false;
    }

    if ( ! ($this->sig = $this->request->getParam('sig')) ) {
      $this->logHelper->log('<<<s no sig');
      return false;
    }
    return true; //everything ok
  }

  /**
   *
   * Check if signature of the received request is correct. 
   * @return true|false
   */
  protected function checkSignature() {
    $key2 =  $this->config->getSecondMd5();

    $hash = md5(
      $this->pos_id
      . $this->session_id
      . $this->ts
      . $key2
    );

    if ($hash == $this->sig) {
      return true;
    } else {
      $this->logHelper->log('r:wrong signature');
      return false;
    }
  }

  /**
   *
   * Moves current order status to history with comment, verify if order exists
   * @param string $msg comment of status
   * @return true|false 
   */
  protected function setOrderStatus($msg) {
    $order = $this->getOrder();
    if ( ! isset($msg)) $msg = '';
    
    if ($order) {
      $order->addStatusToHistory(
        $order->getStatus(),
        Mage::helper('snowpayu')->__($msg)
      );
      return true;
    }
    return false;
  }

  /**
   *
   * Cancels order matching order_id = session_id from PayU
   * @param $msg custom message written in status history
   * @return false on failure, true on correct order status change
   */
  protected function cancelTransaction($msg) {
    $order = $this->getOrder();
    if ($order) {
      $order->cancel();
      return $this->setOrderStatus($msg);
    }
    return false;
  }

  /**
   *
   * Returns order object from session_id param from PayU, which is
   * exacly the same as order_id in shop.
   * @return Mage_Sales_Model_Order
   */
  protected function getOrder() {
    if ( ! isset($this->order)) {
      $this->order = Mage::getModel('sales/order')->loadByIncrementId($this->session_id);
      if ( ! $this->order->getId()) {
        $this->logHelper->log('<<<s no order [object]');
        $this->order = null;
      }
    }
    return $this->order;
  }

  /**
   *
   * Sends e-mail with status change and  writes status down to history
   * @return true|false
   */
  protected function sendOrderEmail() {
    $order = $this->getOrder();
    if ($order) {
      $this->logHelper->log('s:sendNewOrderEmail');
      $order->sendNewOrderEmail();
      $currentStatus = (string) @$this->transaction->getTransactionInfo()->status;
      $currentStatus = $this->config->getStatusesWithCode($currentStatus);
      $this->setOrderStatus(self::$MSG_ORDER_STATUS . $currentStatus . '.');
      return true;
    }
    return false;
  }

  /**
   *
   * Tries to set up an invoice for finished transaction
   */
  protected function setInvoice() {
    $order = $this->getOrder();
    if ($order && $order->canInvoice()) {
      $invoice = $order->prepareInvoice();
      $invoice->register()->capture();
      Mage::getModel('core/resource_transaction')
      ->addObject($invoice)
      ->addObject($invoice->getOrder())
      ->save();
      $this->logHelper->log('s:state processing');
      $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
    } else {
      $this->logHelper->log('s:could not create invoice');
      $this->setOrderStatus(self::$MSG_NO_INVOICE);
    }
  }

  /**
   *
   * Save order to the database, and set the QuoteId from PayU. Unvalidate the quote
   */
  protected function saveOrder() {
    $this->logHelper->log('s:order save');

    $order = $this->getOrder();
    
    if ($order) {
      $order->save();
    }

    $session = Mage::getSingleton('checkout/session');
    $session->setQuoteId($session->getPayUStandardQuoteId(true));
    Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
  }


  /**
   *
   * Check if current PayU transaction is a new order
   * @return true|false
   */
  protected function isNewOrder() {
    $ppStatus = @$this->transaction->getTransactionInfo()->status;
    if (self::$PAYU_CODE_TO_MAGENTO_STATUS[(int) $ppStatus]
        == Mage_Sales_Model_Order::STATE_NEW)
       return true;
    return false;
  }

  /**
   *
   * Check if current PayU transaction is a currently processed order
   * @return true|false
   */
  protected function isProcessingOrder() {
    $ppStatus = @$this->transaction->getTransactionInfo()->status;
    if (self::$PAYU_CODE_TO_MAGENTO_STATUS[(int) $ppStatus]
        == Mage_Sales_Model_Order::STATE_PROCESSING) 
      return true;
    return false;
  }

  /**
   *
   * Check if current PayU transaction is a canceled order
   * @return true|false
   */
  protected function isCanceledOrder() {
    $ppStatus = @$this->transaction->getTransactionInfo()->status;
    if (self::$PAYU_CODE_TO_MAGENTO_STATUS[(int) $ppStatus]
        == Mage_Sales_Model_Order::STATE_CANCELED) {
      return true;
    }
    return false;
  }

  /**
   *
   * Change the Magento order state to 'NEW' in magento database
   */
  protected function setNewOrderState() {
    $order = $this->getOrder();
    $order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
  }

  /**
   *
   * Change the Magento order state to 'PROCESSING' in magento database
   */
  protected function setProcessingOrderState() {
    $this->logHelper->log('s:success code');
    $order = $this->getOrder();
    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
  }

  /**
   *
   * Cancel the order in magento database
   */
  protected function setCanceledOrderState() {
    $order = $this->getOrder();
    if ($order->canCancel()) {
      $order->cancel();
    }
  }

  /**
   *
   * After getting correct response from PayU with
   * transaction status.
   */
  protected function updateOrderState() {

    $validator = Mage::getModel('snowpayu/transactionValidator');
    $validator->setTransaction($this->transaction);

    //Not a proper sum
    if ( ! $validator->checkSumOfTransaction())
      $this->cancelTransaction(self::$MSG_INVALID_SUM);
    //Disallowed payment type
    if ( ! $validator->checkPaymentEnabled())
      $this->setOrderStatus(self::$MSG_INVALID_PAYMENT);

    //Take proper actions depending on the order state
    if ($this->isNewOrder()) {
      $this->sendOrderEmail();
      $this->setNewOrderState();
    } else if ($this->isProcessingOrder()) {
      if ($validator->checkRedundantCode(self::TRANSACTION_FINISHED)) //Check if not a redundant call
      {                                 
        $this->setProcessingOrderState();              
        $this->setInvoice();
      }
    } else if ($this->isCanceledOrder()) {
      $this->setCanceledOrderState();
    }
    $this->saveOrder();
  }

  /**
   *
   * Entry point processing request form PayU
   * @param mixed $request from PayU
   */
  public function processRequest($request) {
    $this->request = $request;
    $this->logHelper->beginReport();
    //Validate the request
    if ($this->checkParams() && $this->checkSignature()) {

      //Get transaction info from PayU
      if ($this->transaction->processTransaction($this->pos_id, $this->session_id))
      {
        //Process the reponse
        $this->updateOrderState();
      }
    }
    $this->logHelper->endReport();
  }
  
  // end class
}
