<?php
/**
  Created By : PayAir Team
  Funnction : Payair_Gate_Model_Observer Class to extend the Mage_Core_Model_Abstract, a checkout observer 
*/
 
class Payair_Gate_Model_Payair extends Mage_Api_Model_Resource_Abstract {
 
	/**
     * Retrieve regions list
     *
     * @param string $country
     * @return array
     */
    public function getRegionId($country, $name)
    {
        try {
            $country = Mage::getModel('directory/country')->loadByCode($country);
        } catch (Mage_Core_Exception $e) {
            $this->_fault('country_not_exists', $e->getMessage());
        }

        if (!$country->getId()) {
            $this->_fault('country_not_exists');
        }

        $result = array();
		foreach ($country->getRegions() as $region) {
            if ( strtolower($region->getName()) == strtolower($name)) {
				return $region->getRegionId();
			}
        }

        return 1;
    }
	
	/**
     * Get allowed product tax classes by rule id
     *
     * @param   int $ruleId
     * @return  array
     */
	private function createCustomer($user) {
		
		$customer = Mage::getModel('customer/customer');
		$customer->website_id = Mage::getModel('core/store')->load( $this->getStoreId() )->getWebsiteId();
		$customer->setStore(Mage::getModel('core/store')->load( $this->getStoreId() ));
		$customer->loadByEmail((string)$user->email);
		if (!$customer->getId()) {
			$pass = uniqid();
			$customer->setEmail($user->email);
			$customer->setFirstname($user->firstName);
			$customer->setLastname($user->lastName);
			$customer->setPassword($pass);
			$customer->sendNewAccountEmail();
			$customer->setConfirmation(null);
			$customer->save();
		}
		
		$customAddress = Mage::getModel("customer/address");
		$customAddress->setCustomerId($customer->getId());
		if ($defaultShippingId = $customer->getDefaultShipping()) {
			$customAddress->load($defaultShippingId); 
		} elseif ($defaultBillingId = $customer->getDefaultBilling()) {
			$customAddress->load($defaultBillingId); 
		}
		try {
			$customAddress->firstname       = $user->firstName;
			$customAddress->lastname        = $user->lastName;
			$customAddress->country_id      = $user->country; //Country code here
			/* NOTE: If country is USA, please set up $address->region also */
			if($user->country == 'US') {
				$customAddress->region_id   = Mage::getModel('gate/payair')->getRegionId('US', $user->state);
			} 
			$customAddress->street          = $user->address1;
			$customAddress->postcode        = $user->zip;
			$customAddress->city            = $user->city;
			$customAddress->telephone       = $user->phoneNr;
			$customAddress->fax             = "";
			$customAddress->company         = "";
			$customAddress->save();
				
			$customAddress->setCustomerId($customer->getId())
				->setIsDefaultBilling(1) 
				->setIsDefaultShipping(1) 
				->setSaveInAddressBook(1);
			$customAddress->save();
		} catch (Exception $ex) {}
	
		return $customer;
	}
}
?>