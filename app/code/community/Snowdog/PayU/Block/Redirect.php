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
 * Redirection form block. Its content is send to PayU at the end of customer checkout 
 * @author michal
 */
class Snowdog_PayU_Block_Redirect extends Mage_Core_Block_Abstract {
  
  protected function _toHtml() {
    $payu = Mage::getSingleton("snowpayu/payu");

    $form = new Varien_Data_Form();

    $form->setAction($payu->getConfig()->getPaymentURI())
      ->setId('snowpayu_payu_checkout')
      ->setName('snowpayu_payu_checkout')
      ->setMethod('POST')
      ->setUseContainer(true);

    foreach ($payu->getRedirectionFormData() as $field => $value) {
      $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
    }

    $html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><style type="text/css">body{background-color:#efefef;font:18px Arial,sans-serif;color:#333;} .info{width:400px;height:200px;position:absolute;top:50%;left:50%;margin-top:-100px;margin-left:-200px;} </style></head><body>';
    $html .= '<div class="info"><p>';
    $html .= $this->__('You will be redirected to PayU in a few seconds.');
    $html .= '</p>';
    $html .= $form->toHtml();
    $html .= '<img src=" ' . $this->getSkinUrl('images/payu/loader.gif') . '" alt="" /></div><script type="text/javascript">document.getElementById("snowpayu_payu_checkout").submit();</script>';
    $html .= '</body></html>';

    return $html;
  }
  
  // end class
}