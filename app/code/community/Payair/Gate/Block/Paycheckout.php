<?php
/**
  Created By : PayAir Team
  Funnction : Payair_Gate_Block_Paycheckout Class to extend the Mage_Core_Block_Template, prepare layout and return html for payair checkout
*/
 
class Payair_Gate_Block_Paycheckout extends Mage_Core_Block_Template {

	protected function _toHtml() {
        $html = parent::_toHtml();
        
		return $html;
    }
    
    protected function _prepareLayout() {
        return parent::_prepareLayout();  
    }
}