<?php
/**
  Created By : PayAir Team
  Funnction : Payair_Gate_Model_Observer Class to extend the Mage_Core_Model_Abstract, a checkout observer 
*/
 
class Payair_Gate_Model_Observer extends Mage_Core_Model_Abstract {
 
	public function createInvoiceAndCapturePayment($observer) {
		try {
			$shipment = $observer->getEvent()->getShipment();
			$order = $shipment->getOrder();

			if (!$order->canInvoice()) {
				return $this;
			}
                        
			$transactionSave = Mage::getModel('core/resource_transaction');

			$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();                        
			$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
			$invoice->register();

   
			$transactionSave = Mage::getModel('core/resource_transaction')
				->addObject($invoice)
				->addObject($invoice->getOrder());

			$transactionSave->save();
 
			// Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The invoice has been created.'));		
		} catch(Exception $e) {
			die($e->getMessage());
		}
	}
}
?>