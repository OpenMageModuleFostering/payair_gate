<?php
/**
  Created By : PayAir Team
  Funnction : Payair_Gate_Block_Paymentjs Class to extend the Mage_Core_Block_Template, return checkout javascript for payair checkout
*/

class Payair_Gate_Block_Paymentjs extends Mage_Core_Block_Template 
{
	/** 
	*  Function return the checkout javascript	
	*/
	
	protected function _toHtml() {
        //$js = '<script type="text/javascript" src="'.$this->getSkinUrl("js/payair/payaircheckout.js").'"></script>';
        $js = '';
        
		return $js;
    }
    
    protected function _prepareLayout() {
        return parent::_prepareLayout();  
    }
}