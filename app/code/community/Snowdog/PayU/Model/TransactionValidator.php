<?php
/**
 *
 * Class responsible for validating incoming transactions events
 * @author Snowdog
 */
class Snowdog_PayU_Model_TransactionValidator extends Mage_Core_Model_Session_Abstract {

    /**
     *
     * Transaction model which we're validating
     * @var Snowdog_PayU_Model_Transaction
     */
    protected $transaction;

    /**
     *
     * Magento order associated with current transaction
     * @var Mage_Sales_Model_Order
     */
    protected $order;

    /**
     *
     * @var Snowdog_PayU_Model_Config
     */
    protected $config;

    /**
     *
     * @var Snowdog_PayU_Helper_Log
     */
    protected $logHelper;

    public function __construct(){
        $this->logHelper = Mage::helper('snowpayu/log');
        $this->config = Mage::getSingleton('snowpayu/config');
    }


    /**
     *
     * Associate transaction with TransactionValidator, also loads the proper Order model
     * @param Snowdog_PayU_Model_Transaction $transaction
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
        $this->order = Mage::getModel('sales/order')->loadByIncrementId($this->transaction->getOrderId());
        if (!$this->order || !$this->order->getId()) {
            $this->logHelper->log('<<<s no order [object]');
            $this->order = null;
        }
    }

    /**
     *
     * Checks if total cost in PayU
     * is equal total cost in shop
     */
    public function checkSumOfTransaction(){
        //round float to whole gr, then multiply by 100 to get gr from zl
        $orderCost = (int)(round($this->order->getBaseGrandTotal(), 2) * 100);
        //in gr
        $transactionCost = (int) @$this->transaction->getTransactionInfo()->amount;

        if(abs($orderCost - $transactionCost) < 1){
            //difference is less then 1 gr, so no one hurts
            return true;
        }else{
            //return false indicating that we should cancel the transaction
            $this->logHelper->log('s:cancel [baseGrandTotal != transaction->amount]');
            return false;
        }
    }

    /**
     *
     * Make sure shop allows same payment method
     * @return true on validation, false on invalid payment
     */
    public function checkPaymentEnabled(){
        $enabled = explode(',', $this->config->getEnabledPaymentTypes());
        $payment = @$this->transaction->getTransactionInfo()->pay_type;
        if(in_array($payment, $enabled))
            return true;
        else {
            $this->logHelper->log('s:disabled payment');
            $this->logHelper->log($payment);
            return false; //Invalid payment
        }
        return true;
    }

     
    /**
     *
     * Checks if the success code sent by the server is redundant
     * @param $status status code
     * @return true - proper |false - redundant
     */
    public function checkRedundantCode($status)
    {
        return $this->transaction->checkIfStatusAlreadyReceived($status);
    }

}