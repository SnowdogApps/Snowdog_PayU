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
 * @package  PayU
 * @author	   Snowdog
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 *
 * Snowdog_PayU_Model_PayU resource model. Handles
 * logging transactions in the database.
 * @author Snowdog
 *
 */
class Snowdog_PayU_Model_Mysql4_PayU extends Mage_Core_Model_Mysql4_Abstract {

  public function _construct() {
    $this->_init('snowpayu/payu', 'payu_id');
    $this->_payuTable = $this->getTable('snowpayu/payu');
  }

  /**
   * 
   * Insert transaction log into the database
   * @param SimpleXMLElement $object transaction info from PayU
   */
  public function logTransaction($object) {
    $insertData['session_id'] = (string) $object->session_id;
    $insertData['pos_id']   = (string) $object->pos_id;
    $insertData['status']   = (string) $object->status;
    $insertData['timestamp']  = (string) date("Y-m-d H:i:s");

    $this->_getWriteAdapter()->beginTransaction();

    try {
      $this->_getWriteAdapter()->insert($this->_payuTable, $insertData);
      $this->_getWriteAdapter()->commit();
    } catch (Exception $e) {
      $this->_getWriteAdapter()->rollBack();
    }
  }

  /**
   * 
   * Gets the latest log for the requested transaction
   * @param int $order_id
   * @return array("payu_id", "session_id", "pos_id", "status", "timestamp")
   */
  public function getLastLogForTransaction($order_id) {
    if (empty($order_id)) {
      return array();
    }

    $select = $this->_getReadAdapter()->select()
      ->from($this->_payuTable)
      ->where('session_id=?', $order_id)
      ->order('timestamp DESC')
      ->order('payu_id DESC');

    return $this->_getReadAdapter()->fetchRow($select);
  }

  /**
   *
   * Check if an transaction already has more than 1 log entry with proper status
   * @param int $session_id
   * @param int $status
   * @return true if more than 1 exists, false if 1 or 0
   */
  public function checkIfStatusAlreadyInDb($session_id, $status) {
    $select = $this->_getReadAdapter()->select()
      ->from($this->_payuTable, "COUNT(*) as num")
      ->where('session_id=?', $session_id)
      ->where('status = ?', $status);
      
    $result = $this->_getReadAdapter()->fetchRow($select);
    
    if ($result['num'] > 1) {
      return false;
    }
    return true;
  }
  
  // end class
}
