<?php

class Snowdog_PayU_Block_Hint extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

	protected $_template = 'payu/hint.phtml';
	 
	public function render(Varien_Data_Form_Element_Abstract $element) {
		return $this->toHtml();
	}

	protected function _getUrlModelClass() {
		return 'core/url';
	}

	public function getUrl($route = '', $params = array()) {
		$res = parent::getUrl($route, $params);
		$res = str_replace("index.php/", "", $res);
		return preg_replace("/&?\/$/","",$res);
	}
}
