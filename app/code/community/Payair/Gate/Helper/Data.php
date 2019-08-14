<?php
/**
  Created By : PayAir Team
  Funnction : Payair_Gate_Helper_Data Class to extend the Mage_Core_Helper_Abstract, to to check if the remote image is ready to load in the dom
*/
 
class Payair_Gate_Helper_Data extends Mage_Core_Helper_Abstract
{	
	/** 
	*  Function to check if the remote image is ready to load in the dom	
	*/
	
	public function is_remote_image_ready($image_url) {
		$ch = curl_init($image_url);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_exec($ch);
		$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		// $retcode > 400 -> not found, $retcode = 200 -> found.
		
		if ($retcode > 400) {
			return false;
		} elseif ($retcode == 200) {
			return true;
		}
		curl_close($ch);
	}
	
	public function getEnvironmentJavascript($page_type='') {
		$payair_env_type = Mage::getStoreConfig('payment/gate/environment');
		if ($payair_env_type == 'development') {
			if($page_type == "checkout") {
				$payair_script = 'https://test.payair.com/embed/js/checkout.js';
			}else{
				$payair_script = 'https://test.payair.com/embed/js/product.js';
			}
		} elseif ($payair_env_type == 'production') {
			if($page_type == "checkout") {
				$payair_script = 'https://payair.com/ms/js/checkout.js';
			}else{
				$payair_script = 'https://payair.com/ms/js/product.js';
			}
		} elseif ($payair_env_type == 'qa') {
			$payair_script = 'https://qa.payair.com/embed/js/checkout.js';
		} else {
			$payair_script = 'https://test.payair.com/embed/js/mobileshopper_dev.js';
		}
		
		return $payair_script;
	}
	
	public function getBannerImages($type = 'background') {
		$payair_checkout_img_url = 'http://payairus.net/images/ecommerce/express_checkout_banner_v3.png';
		$android_small_thumb_url = 'https://www.payair.com/img/android_video_thumb_small.png';
		
		if ($this->is_remote_image_ready($payair_checkout_img_url)) {
			$payair_checkout_img = $payair_checkout_img_url;
		} else {
			// Load from the client library
			$payair_checkout_img = Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL) . 'media/payair/express_checkout_banner_v3.png';			
		}
		
		if ($this->is_remote_image_ready($android_small_thumb_url)) {
			$payair_android_small_img = $android_small_thumb_url;
		} else {
			// Load from the client library
			$payair_android_small_img = Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL) . 'media/payair/android_video_thumb_small.png';			
		}
		if($type == 'background') {
			return $payair_checkout_img;
		} else if($type == 'video') {
			return $payair_android_small_img;
		}
	}
} 
?>