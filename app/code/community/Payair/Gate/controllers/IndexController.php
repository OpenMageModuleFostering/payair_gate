<?php
/* ===================================================
 * @description: PayAir Callback controller
 * ==================================================
 */
 
class Payair_Gate_IndexController extends Mage_Core_Controller_Front_Action {
	
	private $log_file = 'log.txt';
	private $vatPercent = 0;
	public $request = '';
	private $headers = array();
	private $tax = 0;
	private $data_file_extension = '.txt';
	
	/**
     * Initialize controller
     */
	
	public function indexAction() {
		echo "We are in index function";
		$this->request   = $this->getServiceRequest();
		exit;
	}
	
	/**
	* The function which take control over the web service request
	*/
	public function itemAction() {
		$suggested_items = array();                         // Suggested Item Array
		$this->request   = $this->getServiceRequest();      // JSON request from Payair server
		$this->setStoreId();                                // Set Store Id
		$customer = $this->createCustomer( $this->request->user );

		$product_id = $this->request->reference;       // Product Id
		$product_obj = $this->loadProduct( $product_id ); // Load Product by Product Id
		$this->setVatPercent( $customer, $product_obj );
		$item = array(
					"name"            => $this->limitStringLenght( $product_obj->name, 254 ),
					"amount"          => $this->getFormattedPrice($product_obj->price),
					"vat"             => $this->getFormattedPrice( $this->getVatAmount( $product_obj->price, $this->getVatPercent() ) ),
					"quantity"        => "1",
					"description"     => $this->limitStringLenght( $product_obj->short_description, 254 ),
					"sku"             => $this->limitStringLenght( $product_obj->sku, 63 ),
					"vatPercent"      => $this->getVatPercent(),//$this->getFormattedPrice($this->getVatPercent()),
					"imageUrl"        => $this->limitStringLenght((string) Mage::Helper('catalog/image')->init($product_obj,'image'), 1023),
					"url"			  => $this->limitStringLenght( $product_obj->getProductUrl(), 1023 ),
					"longDescription" => $this->limitStringLenght( $product_obj->description, 1499 ),
					"currency"        => $this->getCurrencyISONumber(),
					"sortOrder"       => "1",
					"discounts"       => array(),
					"customValues"	  => $this->setCustomValue( $product_id ),
					"attributes"	  => $this->getAttributes($product_obj)
				  );

		/*$related_products_id_array = $product_obj->getRelatedProductIds(); // Get Related Products
		if ( count($related_products_id_array) > 0 ) {
			foreach ( $related_products_id_array as  $related_product_id ) {
				$related_product = $this->loadProduct($related_product_id); // Load related Product by Product Id
				$suggested_items[] = array( 
					"name" 			  => $related_product->name,
					"amount"		  => $this->getFormattedPrice($related_product->price),
					"vat"			  => 0,
					"quantity" 		  => "1",
					"description" 	  => $related_product->short_description,
					"sku" 			  => $related_product->sku,
					"vatPercent" 	  => 0,
					"imageUrl" 		  => (string) Mage::Helper('catalog/image')->init($related_product,'image'),
					"url"			  => $related_product->getProductUrl(),
					"longDescription" => $related_product->description,
					"currency" 		  => $this->getCurrencyISONumber(),
					"sortOrder" 	  => "1"
				);
			}
	    } */
			
		$response_array = array( 
			"statusCode"     => "SUCCESS", 
			"message"        => "operation successful", 
			"item"           => $item, 
			"suggestedItems" => $suggested_items
			);
			
		$response_json = json_encode($response_array);
		$this->sendResponse($response_json, 'item Action');
    }
	
	/**
	* The function which take control over the web service request for Cart sync
	*/
	public function syncAction() {
		
		$this->request = $this->getServiceRequest();      // JSON request from Payair server
		$this->setStoreId();                                // Set Store Id
		$customer = $this->createCustomer($this->request->user);
		$item = array();		
		$products = $this->getRequestCollection('cart');    // get product data for request array
		
		if ( is_array( $products) && count( $products > 0 ) ) {
			foreach ( $products as $product ) {
				$product_id 	  = $this->getCustomNameValue($product['customValues'], 'reference'); 
				$product_obj      = $this->loadProduct($product_id); // Load Product by Product Id
				$product_quantity = $product['quantity'];
				$this->setVatPercent( $customer, $product_obj );
				$product_price = ( $product['amount']/100 );
				$product_vat = $this->getFormattedPrice( ( $this->getVatAmount( $product_price, $this->getVatPercent() ) ) * $product_quantity );
				
				$options = $product_obj->getOptions();
				$cartOptions = array();
				$configCartOptions = array();
				foreach ($options as $option) {
					$optionData = $option->getData();
					$optionValues = Mage::helper('core')->decorateArray($option->getValues());
					
					$type = $option->getData('type');
					$optionValuesArray = array();
					
					if ( $type == 'field' || $type == 'area' ) {
						$optionValuesArray[$option->getData('default_title')] = $option->getData('option_id');
					} else {
						foreach ( $optionValues as $optionValue ) {
							$optionValueData = $optionValue->getData();
							$optionValuesArray[$optionValueData['default_title']] = $optionValueData['option_type_id'];
						}
					}
					
					foreach ( $product['attributes'] as $attribute ) {
						if ( $attribute['name'] == $optionData['default_title'] ) {
							$selectedAttributes = array();
							$selectedAttributesValue = array();
							foreach ( $attribute['attributeValues'] as $attributeValue ) {
								if ( $attributeValue['chosen']) { 
									$selectedAttributes[] = $optionValuesArray[$attributeValue ['value']];
									if ( $type == 'field' || $type == 'area' ) { 
									$selectedAttributesValue[] = $attributeValue ['value'];
									}						
								}
							}
							if ( $type == 'field' || $type == 'area' ) {
								$cartOptions[$optionData['option_id']] = $selectedAttributesValue[0];
							} else {
								$cartOptions[$optionData['option_id']] = count($selectedAttributes) == 1 ? $selectedAttributes[0] : $selectedAttributes;
							}
						}
					}
				}
				// Add configurable product to the cart
				if ( $product_obj->getTypeId() == 'configurable' ) {
					$configAttributesOptions = $product_obj->getTypeInstance(true)->getConfigurableAttributesAsArray($product_obj);

					foreach ( $configAttributesOptions as  $configAttributesOption ) {
						$attribute_code = $configAttributesOption['attribute_code'];
						$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attribute_code);
						$optionValuesArray = array();
							foreach ( $product['attributes'] as $attribute ) {
								if( $attribute['name'] == $attribute_details->getData('store_label') ) {
									foreach ( $configAttributesOption['values'] as  $value ) {
										$optionValuesArray[$value['default_label']] = $value['value_index'];
										$selectedAttributes = array();
										foreach ( $attribute['attributeValues'] as $attributeValue ) {
											if ( $attributeValue['chosen']) { 
												$selectedAttributes[] = $optionValuesArray[$attributeValue ['value']];
											}
										}
									}
									$configCartOptions[$attribute_details->getData('attribute_id')] = count($selectedAttributes) == 1 ? $selectedAttributes[0] : $selectedAttributes;
								}
							}
						}
					} 

				$item[] = array(
							"name"            => $this->limitStringLenght( $product['name'], 254),
							"amount"          => $product['amount'],
							"vat"             => $product_vat,
							"quantity"        => $product['quantity'],
							"description"     => $this->limitStringLenght( $product['description'], 254),
							"sku"             => $this->limitStringLenght( $product['sku'], 63 ),
							"vatPercent"      => $this->getVatPercent(),//$this->getFormattedPrice($this->getVatPercent()),
							"imageUrl"        => $this->limitStringLenght( $product['imageUrl'], 1023 ),
							"url"			  => $this->limitStringLenght( $product['url'], 1023 ),
							"longDescription" => $this->limitStringLenght( $product['longDescription'], 1499 ),
							"currency"        => $product['currency'],
							"discounts"       => array(),
							"customValues"	  => $product['customValues'],
							"attributes"	  => $this->getAttributes($product_obj, NULL, $cartOptions ),
							"sortOrder"       => $product['sortOrder'],
							"type"            => $product['type']
						  );
				
				$option = array();
				if ( count($cartOptions ) > 0) { 
					$option['product'] = (string) $product_id;
					$option['qty'] = $product_quantity;
					$option['options'] = $cartOptions;
					if ( is_array($configCartOptions) && count($configCartOptions) ) {  //if config proudct attributes exists
						$option['super_attribute'] = $configCartOptions;
					}
					$option_request = new Varien_Object();
					$option_request->setData($option);
					try {
						Mage::getSingleton('checkout/cart')->addProduct($product_obj, $option_request);
					} catch (Exception $ex) {}
				} else { 
					$option['product'] = (string) $product_id;
					$option['qty'] = $product_quantity;
					$option_request = new Varien_Object();
					$option_request->setData($option);
					try {
						Mage::getSingleton('checkout/cart')->addProduct($product_obj, $option_request);
					} catch (Exception $ex) {}
				}
			}
		}
		
		$quoteObj = Mage::getSingleton('checkout/cart')->getQuote();
		$address = $quoteObj->getShippingAddress();
        $quoteObj->assignCustomer($customer); //sets shipping/billing address
        $customerAddressId = $this->getCustomerAddress()->getId();
		$customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
		
		$address->importCustomerAddress($customerAddress)->setSaveInAddressBook(0);
		$address->implodeStreetAddress();
		$quoteObj->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
		$available_shipping_methods = $quoteObj->getShippingAddress()->getShippingRatesCollection();
		$chosenDeliveryMethod = '';
		$chosenDeliveryMethodName = '';
		if ( is_array( $this->getRequestCollection('chosenDeliveryMethod') ) && count ( $this->getRequestCollection('chosenDeliveryMethod') > 0 )   ) {
			$chosenDeliveryMethod = $this->getRequestCollection('chosenDeliveryMethod'); // get chosen delivery method data from payair request array
		} 
		if ( is_array( $chosenDeliveryMethod ) && ( count( $chosenDeliveryMethod ) > 0 ) ) { 
			$chosenDeliveryMethodId = (int)$chosenDeliveryMethod['description'];
			$chosenDeliveryMethodName = $chosenDeliveryMethod['name'];
			
			/*Load the Shipping Rate Model by its ID*/
			$salesQuoteRate = Mage::getModel('sales/quote_address_rate')->load($chosenDeliveryMethodId);
			if ($salesQuoteRate) {
			   $_mcode = $salesQuoteRate->getCode();
			   $_shp_price = $salesQuoteRate->getPrice();
			} 
		} elseif ( count ( $available_shipping_methods ) == 1 ) { 
			foreach( $available_shipping_methods as $_method ) { 
				$_mcode = $_method->getData('code');
				$_shp_price = $_method->getData('price');
			}
		} elseif ( count ( $available_shipping_methods ) > 1 ) { 
			foreach( $available_shipping_methods as $_method ) {
				$_mcode = NULL;
				$_shp_price = 0;
			}
		} 

		$storeObj = $quoteObj->getStore()->load($this->getStoreId());
        $quoteObj->setStore($storeObj);
        $QuoteItems = $quoteObj->getAllItems();

        $quoteObj->getShippingAddress()->setShippingMethod((string) $_mcode)->setCollectShippingRates(true);
        $quoteObj->collectTotals();
        $quoteObj->reserveOrderId();
        $quoteObj->save();
		
		$address = $quoteObj->getShippingAddress();
		
		$quotePaymentObj = $quoteObj->getPayment(); // Get payment object
        $quotePaymentObj->setMethod('gate'); 
        $quoteObj->setPayment($quotePaymentObj); // Set Payair payment method
		
        $rates_collection = Mage::getModel('sales/quote_address_rate')->getCollection()->setAddressFilter($address->getId());
		$rates = array();
		foreach ( $rates_collection as $rate ){
		    if ( !$rate->isDeleted() && $rate->getCarrierInstance() && Mage::getStoreConfig('carriers/'.$rate->getCarrier().'/active', $this->getStoreId())) { 
                if ( !isset( $rates[$rate->getCarrier()] ) ) {
                    $rates[$rate->getCarrier()] = array();
                }
                $rates[$rate->getCarrier()] = $rate->getData();
            }
		}

		$subTotal = Mage::getSingleton('checkout/cart')->getQuote()->getSubtotal();
		$grandTotal = ( Mage::getSingleton('checkout/cart')->getQuote()->getGrandTotal() ) ;       // total amount of the products
		$totalVat  = $grandTotal - ($subTotal + $_shp_price);   // total vat of the product

		$deliveryMethods = $this->getDeliveryMethods( $rates, $chosenDeliveryMethodName );  // get delivery methods array 

		//  Create cart respone to the mobile app	
		$response_array = array(
							"statusCode"      => "SUCCESS",
							"message"         => "operation successful",
							"cart"            => $item, 
							"deliveryMethods" => $deliveryMethods,
							"totalAmount"     => $this->getFormattedPrice($grandTotal),
							"totalVat"        => $this->getFormattedPrice($totalVat)
							);
		$response_json = json_encode($response_array);
		$this->sendResponse($response_json, 'sync Action');
	

   }
   /**
	* To pre varify the cart price and amount.
	*/
   public function preverifyAction() {
   
		$this->request = $this->getServiceRequest();      // JSON request from Payair server
		$this->setStoreId();                                // Set Store Id
		
		$customer = $this->createCustomer($this->request->user);
		$orderReference = $this->request->orderReference;
		$payairReference = $this->request->payairReference;
		$totalAmount = $this->request->totalAmount;
		$totalVat = $this->request->totalVat;
		$deliveryMethods = $this->getRequestCollection('chosenDeliveryMethod');  // get delivery methods for request array
		
		$method_id = $deliveryMethods['description']; // get method id

		if ( empty( $method_id ) ) {
			// Get the very first shipping method ID
			$method_id = $this->getDefaultShippingMethodId($this->getStoreId());
		}
		
		$salesQuoteRate = Mage::getModel('sales/quote_address_rate')->load( (int) $method_id);
		
		if ( $salesQuoteRate ) {
		   $_mcode = $salesQuoteRate->getCode();
		}  
		
		Mage::getSingleton('checkout/cart')->truncate(); 
	
		$products = $this->getRequestCollection('cart');    // get product data for request array
		
		if( is_array( $products) && count( $products > 0 ) ) {
			foreach ( $products as $product ) {
				$product_id 	  = $this->getCustomNameValue($product['customValues'], 'reference');
				$product_obj      = $this->loadProduct($product_id); // Load Product by Product Id
				$product_quantity = $product['quantity'];
			
				$options = $product_obj->getOptions();
				$cartOptions = array();
				$ConfigCartOptions = array();
				foreach ( $options as $option ) {
					$optionData = $option->getData();
					$optionValues = Mage::helper('core')->decorateArray($option->getValues());
					
					$type = $option->getData('type');
					$optionValuesArray = array();
					
					if ( $type == 'field' || $type == 'area' ) {
						$optionValuesArray[$option->getData('default_title')] = $option->getData('option_id');
					} else {
						foreach ( $optionValues as $optionValue ) {
							$optionValueData = $optionValue->getData();
							
							$optionValuesArray[$optionValueData['default_title']] = $optionValueData['option_type_id'];
						}
					}
					
					foreach ( $product['attributes'] as $attribute ) {
						if ( $attribute['name'] == $optionData['default_title'] ) {
							$selectedAttributes = array();
							$selectedAttributesValue = array();
							foreach ( $attribute['attributeValues'] as $attributeValue ) {
								if ( $attributeValue['chosen']) { 
									$selectedAttributes[] = $optionValuesArray[$attributeValue ['value']];
									if ( $type == 'field' || $type == 'area' ) { 
									$selectedAttributesValue[] = $attributeValue ['value'];
									}
															
								}
							}
							if ( $type == 'field' || $type == 'area' ) {
								$cartOptions[$optionData['option_id']] = $selectedAttributesValue[0];
							} else {
								$cartOptions[$optionData['option_id']] = count($selectedAttributes) == 1 ? $selectedAttributes[0] : $selectedAttributes;
							}
						}
					}
				}
				// Add configurable product to the cart
				if ( $product_obj->getTypeId() == 'configurable' ) {
					$configAttributesOptions = $product_obj->getTypeInstance(true)->getConfigurableAttributesAsArray($product_obj);

					foreach ( $configAttributesOptions as  $configAttributesOption ) {
						$attribute_code = $configAttributesOption['attribute_code'];
						$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attribute_code);
						$optionValuesArray = array();
							foreach ( $product['attributes'] as $attribute ) {
								if( $attribute['name'] == $attribute_details->getData('store_label') ) {
									foreach ( $configAttributesOption['values'] as  $value ) {
										$optionValuesArray[$value['default_label']] = $value['value_index'];
										$selectedAttributes = array();
										foreach( $attribute['attributeValues'] as $attributeValue ) {
											if( $attributeValue['chosen']) { 
												$selectedAttributes[] = $optionValuesArray[$attributeValue ['value']];
											}
										}
									}
									$configCartOptions[$attribute_details->getData('attribute_id')] = count($selectedAttributes) == 1 ? $selectedAttributes[0] : $selectedAttributes;
								}
							}
						}
					} 
				 isset( $configCartOptions ) ? $configCartOptions : $configCartOptions = NULL;  
				$option = array();
				if ( count($cartOptions) > 0 ) { 
					$option['product'] = (string) $product_id;
					$option['qty'] = $product_quantity;
					$option['options'] = $cartOptions;
					if ( is_array($configCartOptions) && count($configCartOptions) ) {  //if config proudct attributes exists
						$option['super_attribute'] = $configCartOptions;
					}
					$option_request = new Varien_Object();
					$option_request->setData($option);
					try {
						Mage::getSingleton('checkout/cart')->addProduct($product_obj, $option_request);
					} catch (Exception $ex) {}
				} else { 
					$option['product'] = (string) $product_id;
					$option['qty'] = $product_quantity;
					$option_request = new Varien_Object();
					$option_request->setData($option);
					try {
						Mage::getSingleton('checkout/cart')->addProduct($product_obj, $option_request);
					} catch (Exception $ex) {}
				}
			}
		}
		$quoteObj = Mage::getSingleton('checkout/cart')->getQuote();

        $quoteObj->assignCustomer($customer); //sets shipping/billing address
        
		$storeObj = $quoteObj->getStore()->load($this->getStoreId());
        $quoteObj->setStore($storeObj);
        $QuoteItems = $quoteObj->getAllItems();

        $quoteObj->getShippingAddress()->setShippingMethod((string) $_mcode)->setCollectShippingRates(true);
        $quoteObj->collectTotals();
        $quoteObj->reserveOrderId();
        $quoteObj->save();
		
		$quotePaymentObj = $quoteObj->getPayment();
        $quotePaymentObj->setMethod('gate');
        $quotePaymentObj->setTransactionId(uniqid());
        $quoteObj->setPayment($quotePaymentObj);
        
		$convertQuoteObj = Mage::getSingleton('sales/convert_quote');
        $orderObj = $convertQuoteObj->addressToOrder($quoteObj->getShippingAddress());
        $convertQuoteObj->paymentToOrderPayment($quotePaymentObj);
	    $orderObj->setBillingAddress($convertQuoteObj->addressToOrderAddress($quoteObj->getBillingAddress()));
        $orderObj->setShippingAddress($convertQuoteObj->addressToOrderAddress($quoteObj->getShippingAddress()));
        $orderObj->setPayment($convertQuoteObj->paymentToOrderPayment($quoteObj->getPayment()));
        $orderObj->setShipping($customer->getShippingRelatedInfo());

        foreach ( $QuoteItems as $item ) {
            $orderItem = $convertQuoteObj->itemToOrderItem($item);
            if ( $item->getParentItem() ) {
                $orderItem->setParentItem($orderObj->getItemByQuoteItemId($item->getParentItem()->getId()));
            }
            $orderObj->addItem($orderItem);
			Mage::getSingleton('cataloginventory/stock')->registerItemSale($orderItem);
        }

        $orderObj->setCanShipPartiallyItem(false);
        $orderObj->getPayment()->setTransactionId(str_pad((int)$this->request->payairReference, 8, "0", STR_PAD_LEFT));
        $orderObj->place();      
        $orderId = $orderObj->getIncrementId(); 
        $orderObj->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, "Payair has set the order status to Pending as the order is awaiting authorization.");
        $orderObj->save();
        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->setOrderPaymentObject($orderObj->getPayment());
        $transaction->setOrder($orderObj);
        $transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER);
        $transaction->save();

		//  Create cart respone to the mobile app	
		$response_array = array(
							"statusCode"           => "SUCCESS",
							"message"              => "operation successful",
							"orderReference"       => $orderId,
							"payairReference"      => $payairReference,
							"cart"                 => $products, 
							"chosenDeliveryMethod" => $deliveryMethods,
							"totalAmount" 	       => $totalAmount,
							"totalVat"             => $totalVat
							);
		
		$response_json = json_encode($response_array);
		$this->sendResponse($response_json, 'preverify Action');
		
   }
   
   /**
	* To get the final payment confirmation.
	*/
   public function resultAction() {
   
		$this->request = $this->getServiceRequest();      // JSON request from Payair server                          
		$orderReference = $this->request->orderReference;
		$payairReference = $this->request->payairReference;
		$transactionStatus = $this->request->transactionStatus;
		$transactionStatusMessage = $this->request->transactionStatusMessage;
		$payairExternalReference = $this->request->payairExternalReference;
		$this->setStoreId();
		//$totalAmount = $this->request->totalAmount;
		//$totalVat = $this->request->totalVat;
		$products = $this->getRequestCollection('cart');    // get product data for request array
		$customValueArr = array();
		if( is_array( $products) && count( $products > 0 ) ) {
			foreach($products as $product) {
				if( !empty ( $product['customValues'] ) ) {
					$customValueArr = $product['customValues'];
				}
			}
		}
		
		$file_ref_name    = $this->getCustomNameValue($customValueArr, 'fileRefNo');   // get the file name
		$deliveryMethods = $this->getRequestCollection('chosenDeliveryMethod');    // get delivery methods for request array
		
		if ( $transactionStatus == 1 ) {
			if ( !empty ( $file_ref_name ) ) {
				$fileContentArr = json_decode($this->getFileContent($file_ref_name),true);
				$fileContentArr['paystatus'] = 'success';
				$this->writeDataInFile($fileContentArr, $file_ref_name, 'w');
				//$payair_reference = $array['payair_reference'];
			}
			$order = Mage::getModel('sales/order')->loadByIncrementId($orderReference);
			$order->sendNewOrderEmail();
			$order_status = $this->getConfig('gate_order_status', $this->getStoreId()); //get the order status from the admin config
			
			$this->setOrderState( $order, $order_status );
			
			if ( !empty ( $file_ref_name ) ) {
				$this->deleteFile( $file_ref_name );        //delete the temp file in the log
			}
			
			$sanp_view = Mage::getStoreConfig('payment/gate/gate_snap_view', $this->getStoreId());
			
			if ( $sanp_view == 1 ) {   
				$merchant_ref = Mage::getStoreConfig('payment/gate/gate_marchantref', $this->getStoreId());
				$this->completedOrderNotification($merchant_ref);
			}
		} else {
			//load your order by order_id
			$order = Mage::getModel('sales/order')->load($orderReference);
			$order->setState( Mage_Sales_Model_Order::STATE_CANCELED ); //Order cancel status
			$order->save();
		}
		
		$receipt_logo = Mage::getStoreConfig('payment/gate/gate_receipt_logo', $this->getStoreId());
		$receipt_msg_top = Mage::getStoreConfig('payment/gate/gate_order_info_top', $this->getStoreId()); 
		$receipt_msg_bottom = Mage::getStoreConfig('payment/gate/gate_order_info_bottom', $this->getStoreId());
		
		$receipt_data = array(
							array("name"  => "receiptLogo", "value" => $receipt_logo),
							array("name"  => "receiptTopMsg", "value" => $receipt_msg_top),
							array("name" => "receiptBottomMsg", "value" => $receipt_msg_bottom)
						);

		
		$response_array = array(
							"statusCode"               => "SUCCESS",
							"message"         		   => "operation successful",
							"transactionStatus"        => $transactionStatus,
							"transactionStatusMessage" => $transactionStatusMessage,
							"payairExternalReference"  => $payairExternalReference,
							"orderReference"           => $orderReference,
							"customReceiptData"		   => $receipt_data,
							"payairReference"          => $payairReference,
							"cart"                     => $products, 
							"chosenDeliveryMethods"    => $deliveryMethods
							);
		
		$response_json = json_encode($response_array);
		$this->sendResponse($response_json, 'result Action');
	}
	
	/**
	* The function used to get the ajax request.
	* @return null
	*/
	public function payStatusAction () {
		$file_name = $this->getRequest()->getParam('link');
		$pay_status = $this->readData($file_name, 'paystatus');
		if ( $pay_status != 'running' ) {
			Mage::getSingleton('checkout/cart')->truncate();
			Mage::getSingleton('checkout/session')->clear();
		}
		
		echo $this->readData($file_name, 'paystatus');
	}
	
	/**
	* The function used to write the data in the temporary file.
	* @return null
	*/
	
	public function writeDataInFile( $data, $data_file, $file_mode = 'a' ) {
		$tmp_file_name = $data_file . $this->data_file_extension;
		$file_location = $this->getFilePath() . $tmp_file_name; 
		@chmod($file_location,0777);
		$handle = fopen($file_location, $file_mode);
		if ($handle) {
			@fwrite($handle, json_encode($data));
			@fclose($handle);
		} else {
			Mage::log('Unable to write the data in the file "'.$file_location. '". No such file or direcrory found');
		}
    }
	
	/**
	* The function used to read the dataFile saved from the cart action.
	* @return Array
	*/
	
	public function readData( $file, $key = NULL ) {
		$tmp_file_path = $this->getFilePath();
		$_fileToImportLocal = $tmp_file_path.DS.$file . $this->data_file_extension;
		$flocal = new Varien_Io_File();
		$fp = $flocal->Open(array('path' => $tmp_file_path),'w+',0777);
		if ($fp) {
			$str = $flocal->read($_fileToImportLocal);
			$data_array = json_decode($str,true);
			if (@array_key_exists($key,$data_array)) {
				$data = $data_array[$key];
				return $data;
			} else {
				return null;
			}
		} else {
			Mage::log('Unable to open the file "'.$_fileToImportLocal. '". No such file or direcrory found');
		}
	}
	
	/**
	* The function used to delete temporary file.
	* @return null
	*/
	
	public function deleteFile( $file_name ) { 
		$tmp_file_name = $file_name . $this->data_file_extension;
		$file_location = $this->getFilePath() . $tmp_file_name; 
		try { 
			 unlink( $file_location );
		} catch ( Exception $e ) { 
				echo 'Caught exception: '. $e->getMessage();
		}
    }
	
	/**
	* The function used to get the temporary data file path.
	* @return String
	*/
	
	public function getFilePath() {
        $base_dir = Mage::getBaseDir();
		$tmp_file_dir = $base_dir.DS.'var/log/';
		
		return $tmp_file_dir;
	}
	
	private function getFileContent( $file_name ) {
		$tmp_file_path = $this->getFilePath();
		$_fileToImportLocal = $tmp_file_path.DS.$file_name . $this->data_file_extension;
		$flocal = new Varien_Io_File();
		$fp = $flocal->Open( array ( 'path' => $tmp_file_path ),'w+',0777 );
		return ($fp) ? $flocal->read($_fileToImportLocal) : '';
	}
	
	
	 /**
     * handle JSON request from the payair server
     *
     * @param   
     * @return  request array
     */
	private function getServiceRequest() {
		$json = file_get_contents('php://input');
		$json = utf8_encode($json); 
		Mage::log("\n Request JSON is : \n" . print_r($json, true), null, $this->log_file);
		return json_decode($json);
	}
	
	
	/**
     * send JSON request to the payair server
     *
     * @param   string $data, $controller_name
     * @return  
     */
	 
	private function sendResponse($data, $controller_name) {
		Mage::log("\n send response from " . $controller_name . "\n" . print_r($data, true), null, $this->log_file);
		$this->getResponse()->setHeader('Content-type', 'application/json', true);
		echo $data;
	}
   
   /**
    * The function Return the first method ID
	* @param $store_id
    * @return Integer
    */
	
	public function getDefaultShippingMethodId($store_id) {
		$rates_collection = Mage::getModel('sales/quote_address_rate')->getCollection();
        $rates = array();
		foreach ($rates_collection as $rate) {
            if ( !$rate->isDeleted() && $rate->getCarrierInstance() && Mage::getStoreConfig('carriers/'.$rate->getCarrier().'/active', $store_id)) {
                if (!isset($rates[$rate->getCarrier()])) {
                    $rates[$rate->getCarrier()] = array();
                }
                $rates[$rate->getCarrier()] = $rate;
            }
        }  
		$keys = @array_keys($rates);
		$method  = $rates[$keys[0]];
		// Return the first method ID
		return $method->getRateId();
	}
	
	/**
	* The function encode the string.
	* @return string
	*/
	public function encodeString ( $string ) {
		return urlencode(trim($string));
	}
	
	/**
	* The function generates MAC.
	*
	* @return String
	*/
	
	protected function mac($string, $storeId) {
        $hash = $string . $this->getConfig('gate_secret', $storeId);
		
		return hash('sha256', $hash);
    } 
	
	/**
	* The function returns the configuration object.
	*
	* @return String
	*/
	protected function getConfig($item, $storeId) {
        return Mage::getStoreConfig('payment/gate/' . $item, $storeId);
    }
	
   /**
	* The function returns the request path .
	*
	* @return String
	*/
   public function getRequestPath() {
		$merchantAccountRef = $this->getConfig('gate_marchantref', $this->getStoreId());
		return "/rest/merchant/".$merchantAccountRef."/checkout"; 
		
	}
	
	private function stringMac ($string) {
		return hash('sha256', $string);
	}
	
	/**
	* The function returns the authorization.
	*
	* @param $date, $timestamp
	* @return String
	*/
	function getAuthorization($data,$timestamp) {
		$secretKey = $this->getConfig('gate_secret', $this->getStoreId()); 
		$merchantAccountRef = $this->getConfig('gate_marchantref', $this->getStoreId());
		$stringToSign = ( $timestamp) . $data . $this->getRequestPath() . $secretKey;
		$signature = hash_hmac('sha256', $stringToSign, $secretKey,false );
		
		$authorization = "PAA" . " " . base64_encode( $merchantAccountRef . ':' . $signature );
		return $authorization;
	}
	
	/**
	* The function returns the header.
	*
	* @param String $data
	* @return String $header
	*/
	function getHeaders($data) {
		$timestamp = time() * 1000;
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'X-TIMESTAMP: ' . $timestamp;
		$headers[] = 'X-WEBSERVICEVERSION: 4.1';
		$headers[] = 'Authorization: ' . $this->getAuthorization($data,$timestamp);
		return $headers;
	}
	
	/**
	* The function POST the data through the curl.
	*
	* @param String $data
	* @return result
	*/
	
	function sendRequest($data) { 	
	
        $headers = $this->getHeaders($data);
		
		$environment = $this->getConfig('environment', $this->getStoreId());
		$merchantAccountRef = $this->getConfig('gate_marchantref', $this->getStoreId());
		
		if ( $environment == 'production' ) {
			$address = 'payair.com';
		} else {
			$address = 'test.payair.com';
		} 
		
		$httpfile = "/rest/merchant/".$merchantAccountRef."/checkout";
        $port = 443;
        $ssl = "ssl://";
        
		$fp = fsockopen( $ssl.$address, $port , $errno, $errstr, 30 );
		if (!$fp) {
		    echo "$errstr ($errno)<br />\n";
		} else {
		    $out = "POST $httpfile HTTP/1.1\r\n";
		    $out .= "Host: $address\r\n";
		    
		    foreach ($headers as $header) {
		    	  $out .= "$header\r\n";
		    }
		    
		    $out .= "Content-Length: ".strlen($data)."\r\n";
		    $out .= "Connection: Close\r\n\r\n";
		    $out .= $data;
		
		    fwrite($fp, $out);
		    $reuslt = "";
		    while (!feof($fp)) {
		        @$result .=  fgets($fp, 1024);
		    }
		    fclose($fp);
		}
		return $result;
	}
	
   /**
	* To create QR code at shopping cart page.
	*/
	
	public function cartAction() {
	
		$this->setStoreId();
		$data_file_name = uniqid();
		$item_array = array();
		$items_in_cart = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
		
		if ( count($items_in_cart) == 0 ) {
            echo "There are no items in the cart";
            exit;
        }
		
		$countryCode = Mage::getStoreConfig('general/country/default', $this->getStoreId());
        Mage::getSingleton('checkout/cart')->getQuote()->getShippingAddress()
            ->setCountryId($countryCode)
            ->setCity('')
            ->setPostcode('')
            ->setRegionId('')
            ->setRegion('')
            ->setCollectShippingRates(true);
        Mage::getSingleton('checkout/cart')->getQuote()->save();
        Mage::getSingleton('checkout/cart')->init();
        Mage::getSingleton('checkout/cart')->save();
		
		foreach ( $items_in_cart as $item ) {
			$product_obj      = $this->loadProduct($item->getProductId()); // Load Product by Product Id
			// Get product options (for custom and configurable products)
            $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
			if ( empty( $options['options'] ) ) {
				$options['options'] = NULL;
			}
			$cartOptionArr = array();
			if ( is_array ( $options['options'] ) && count( $options['options'] ) > 0 ) {
				foreach ( $options['options'] as $option ) {
					$cartOptionArr[ $option['option_id'] ] = $option['option_value'];
				}
			}
			$item_array[] = array(
				"name"            => $this->limitStringLenght( $product_obj->name, 254 ),
				"amount"          => $this->getFormattedPrice($product_obj->price),
				"vat"             => $this->getFormattedPrice( $this->getVatAmount( $product_obj->price, $this->getVatPercent() ) ),
				"quantity"        => $item->getQty(),
				"description"     => $this->limitStringLenght( $product_obj->short_description, 254 ),
				"sku"             => $this->limitStringLenght( $product_obj->sku, 63 ),
				"vatPercent"      => $this->getFormattedPrice($this->getVatPercent()),
				"imageUrl"        => $this->limitStringLenght((string) Mage::Helper('catalog/image')->init($product_obj,'image'),1023),
				"url"			  => $this->limitStringLenght( $product_obj->getProductUrl(), 1023),
				"longDescription" => $this->limitStringLenght( $product_obj->description, 1499 ),
				"currency"        => $this->getCurrencyISONumber(),
				"sortOrder"       => "1",
				"discounts"       => array(),
				"customValues"	  => $this->setCustomValue( $item->getProductId(), $data_file_name ),//$this->setCustomValue('reference', $item->getProductId()),
				"attributes"	  => $this->getAttributes($product_obj, $cartOptionArr)
			  );
		
		}
		
		$grandTotal = Mage::getSingleton('checkout/cart')->getQuote()->getGrandTotal() ;        // total amount of the products
		$subTotal = Mage::getSingleton('checkout/cart')->getQuote()->getSubtotal();
		$totalVat  = $grandTotal - $subTotal;
		
		$data_array = array(
			"currency" 	  => $this->getCurrencyISONumber(),
			"totalAmount" => $this->getFormattedPrice($grandTotal),
			"totalVat"	  => $this->getFormattedPrice($totalVat),
			"cart" 		  => $item_array
			);	
		
		$transaction_status = array( 'paystatus' => 'running');
		// Write the data to the temporay file @dataFile
		$this->writeDataInFile( $transaction_status, $data_file_name );
		
		$data = json_encode($data_array); 
		Mage::log("\n response JSON methods in INDEX CONTROLLER  Cart ACTION: \n" . print_r($data, true), null, 'log.txt');

		// Post Checkout request on Merchant URL	
		$result = $this->sendRequest($data); 

		$response = explode('{', $result);
		$data = array();
		if ( is_array($response) ) {		
			foreach ( $response as $key => $value ) {
				$res = explode('"', $value);
				if (isset($res[1])) { $res[1]; } else { $res[1] = NULL;}
				if (isset($res[3])) { $res[3]; } else { $res[3] = NULL;}
				if (isset($res[5])) { $res[5]; } else { $res[5] = NULL;}
				if (isset($res[7])) { $res[7]; } else { $res[7] = NULL;}
				if (isset($res[11])) { $res[11]; } else { $res[11] = NULL;}
				if (isset($res[13])) { $res[13]; } else { $res[13] = NULL;}
				$data[$res[1]] = $res[3];
				$data[$res[5]] = $res[7];
				$data[$res[11]] = $res[13];
			}
		} 
		
		$cartResponseArr = array();
		if ( $data['statusMessage'] != 'Your request was processed successfully' ) {
			$cartResponseArr['file'] = $data_file_name;
			$cartResponseArr['text'] = $data['statusMessage'];
			echo json_encode($cartResponseArr);
		} else { 
			// Check if the QR data was set in the string			
			if ( stripos($result, 'qrData') !== false && $data['qrData'] !='' ) {
				$cartResponseArr['file'] = $data_file_name;
				$cartResponseArr['text'] = $data['qrData'];
				echo json_encode($cartResponseArr);
			} else {
				// Something went wrong
				//echo 'Error. Can not get QR code. Please contact admin';
				$cartResponseArr['text'] = 'Error. Can not get QR code. Please contact admin';
				$cartResponseArr['file'] = $data_file_name;
				echo json_encode($cartResponseArr);
			}
		}
		exit;
    }
	
	
   /**
     * Get value of the Custom Array
     *
     * @param   $customArrays, $identifier
     * @return  Custom Value
     */
	private function getCustomNameValue ($customArrays, $identifier) {
		foreach ($customArrays as $id => $customArray) {
			if ( in_array( $identifier, $customArray ) ) {
				return $customArrays[$id]['value'];
			}
		}

		return '';
	}
   
	
	/**
     * Set store id
     *
     * @param   int $store id
     * @return  int $store id
     */
	private function setStoreId ( $storeId = NULL) {
		if ( empty($storeId) ) {
			$this->store_id = Mage::app()->getStore()->getStoreId();
		} else {
			$this->store_id = $storeId;
		}
	}
	
	/**
     * Get store id
     *
     * @return  int $store id
     */
	private function getStoreId() {
		return $this->store_id;
	}
	
	/**
     * Load product from 
     *
     * @param  $product_id
     * @return Product Object
     */
	private function loadProduct ( $product_id ) {
		$product = Mage::getModel('catalog/product')->setStoreId( $this->getStoreId() );
		$product->load( (string) $product_id);
		return $product;
	}
	
	/**
     * Get Currency ISO number based upon currency code
     *
     * @return  int $ISONumber
     */
	private function getCurrencyISONumber () { 
		$currency_code = Mage::app()->getStore($this->getStoreId())->getCurrentCurrencyCode();
		switch ( $currency_code ) {
			case 'SWE' :
				$ISONumber = 752;
				break;
			case 'USD' :
			default:
				$ISONumber = 840;
		}
		
		return $ISONumber;
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
		$this->setCustomerAddress($customAddress);
		
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
	
	/**
     * Set Customer addres
     *
     * @param   Obj $customAddress
     */
	private function setCustomerAddress ($customAddress) {
		$this->customAddress = $customAddress;
	}
	
	/**
     * get Customer addres
     *
     * @return  customAddress
     */
	private function getCustomerAddress() {
		return $this->customAddress;
	}
	
	/**
	* The function returns the customer tax class id .
	* @param $customer
	* @return int
	*/
	private function getCustomerTaxClassId ( $customer ) {
		$class_id = Mage::getModel('customer/group')->getTaxClassId( $customer->getData('group_id') );
		return $class_id;
	}
	
	/**
	* The function returns the tax rate.
	* @param $customer, $product_obj
	* @return Float
	*/
	private function setVatPercent($customer, $product_obj) {
       $calculator    = Mage::getSingleton('tax/calculation');
       $request       = $calculator->getRateRequest(
							$customer->getDefaultShippingAddress(), 
							$customer->getDefaultBillingAddress(), 
							$this->getCustomerTaxClassId($customer), 
							Mage::getModel('core/store')->load($this->getStoreId())
						 );
		$tax = 0;
		
		if ( $product_obj->getTaxClassId() == 0 ) {
			$this->vatPercent = $tax;
		} else {
			$request->setProductClassId($product_obj->getTaxClassId());
			$rate          = $calculator->getRate($request);
			$applied_rates = $calculator->getAppliedRates($request);

			foreach ($applied_rates as $t) {
			   $tax       = $tax + $t['percent'];
			}
			$this->vatPercent = $tax;
		}	
		
		return $this; 
    }
	
	/**
     * Get Vat Percentage
     *
     * @return  VatPercent
     */
	private function getVatPercent () {
	
		return $this->vatPercent;
	}
	
	/**
     * Get vat amount
     *
     * @param   $price, $taxRate
     * @return  customAddress
     */
	private function getVatAmount ($price, $taxRate) {
		return $price * ( $taxRate / 100 );
	}
	
	/**
     * Set Custom Value
     *
     * @param   $name, $value
     * @return  Array
     */
	private function setCustomValue ( $product_id, $data_file_name = NULL ) {
		if( empty($data_file_name) || $data_file_name == NULL  ) {
			$customValueArr = array( 
							array( 'name' => 'reference', 'value' => $product_id )
						);
		} else {
			$customValueArr = array( 
							array( 'name' => 'reference', 'value' => $product_id ), 
							array( 'name' => 'fileRefNo','value' => $data_file_name )
						);
		}
		return $customValueArr ;
	}

	/**
     * get attribute name
     *
     * @param   $product_obj
     * @return  attribute array
     */
	public function getAttributes ($product_obj, $cartOptionArr = array(), $syncCartOptionArr = array() ) { 
		
		switch ( $product_obj->getTypeId() ) { 
			case 'simple':
				$attributes = $this->getSimpleProductAttributes($product_obj, $cartOptionArr, $syncCartOptionArr);
				break;
			case 'configurable':
				$attributes = $this->getConfigurableProductAttributes($product_obj);
				break;
			default:
				$attributes = array();
				break;
		
		}

		return $attributes;
	}
	
	/**
     * get simple product attributes
     *
     * @param   $product_obj
     * @return  attribute array
     */
	private function getSimpleProductAttributes ($product_obj, $cartOptionArr = array(), $syncCartOptionArr = array()) {
		
		$options = Mage::helper('core')->decorateArray($product_obj->getOptions());
		if ( is_array($options) && count($options) > 0 ) {
			$attributes = array();
			$total_options = count($options);
			$i = 1;
			foreach ($options as $option) {
				$optionData = $option->getData();
				
				switch ( strtolower(trim($optionData['type'])) ) {
					case 'drop_down':
					case 'radio':
						$optionType = "RADIO";
						break;
					case 'checkbox':
						$optionType = "CHECKBOX";
						break;
					case 'field':
					case 'area':
					default:
						$optionType = "INPUT";
						break;
				}
				
				$sort_order = ($total_options - $optionData['sort_order']) + 1; //calulate sort order in reverse to magento
				
				if( $sort_order <= 0 ) {
					$sort_order = $last_order - 1;	
				}
				
				$attributes[] = array(
					"type" 			   => $optionType,
					"mandatory" 	   => $optionData['is_require'] ? true : false,
					"name" 	  		   => $optionData['default_title'],
					"attributeValues"  => $this->getAttributeValues($option, $product_obj, $cartOptionArr, $syncCartOptionArr),
					"customIdentifier" => $optionData['option_id'],
					"sortOrder" 	   => ( $sort_order > 0 ) ? $sort_order : 1
					);
				$last_order = $sort_order;
			}
		} 
		return !empty($attributes) ? $attributes : array();
	}
	
	/**
     * get simple product attributes value
     *
     * @param   option $product_obj
     * @return  attribute value array
     */
	private function getAttributeValues ($option, $product_obj, $cartOptionArr = array(), $syncCartOptionArr = array() ) {
		$optionValues = Mage::helper('core')->decorateArray($option->getValues());
		$total_options = count($optionValues);
		$attributeValues = array();
		$type = $option->getData('type');
		
		// If items are selected in cart
		$cartOptionValueArr = array();
		$option->getData('option_id');
		if ( is_array ($cartOptionArr) && count($cartOptionArr) > 0 ) {
			$cartOptionValue = $cartOptionArr[$option->getData('option_id')];
			$cartOptionValueArr = explode(',', $cartOptionValue);
			
		} elseif ( is_array ($syncCartOptionArr) && count($syncCartOptionArr) > 0 ) {
			$cartOptionValue = $syncCartOptionArr[$option->getData('option_id')];
			if ( is_array ( $cartOptionValue ) && count ( $cartOptionValue ) > 1 ) {
				$cartOptionValueArr = $cartOptionValue;
			} else {
				$cartOptionValueArr[] = $cartOptionValue;
			}

		}
		if ( $type == 'field' || $type == 'area' ) { 
			$cartOptionValueArr = array_filter($cartOptionValueArr);
			$sort_order = ($total_options - $option->getData('sort_order')) + 1; //calulate sort order in reverse to magento
			
			$attributeValues[] = array(
				"value"            => !empty($cartOptionValueArr) ? $cartOptionValueArr[0] : " ", 
				"chosen"           => true, 
				"amount"           => $this->getFormattedPrice($option->getData('default_price')), 
				"vat" 			   => $this->getFormattedPrice( $this->getVatAmount( $option->getData('default_price'), $this->getVatPercent() ) ), 
				"overrideImageUrl" => false,
				"customIdentifier" => $option->getData('option_id'),
				"sortOrder"        => ( $sort_order > 0 ) ? $sort_order : 1
				);
		} else {
			$last_order = 0;
			foreach ( $optionValues as $optionValue ) {
				$optionValueData = $optionValue->getData();
				$sort_order = ($total_options - $optionValueData['sort_order']) + 1; //calulate sort order in reverse to magento
					if ( $sort_order <= 0 ) {
						$sort_order = $last_order - 1;
					}

				$attributeValues[] = array(
					"value"            => $optionValueData['default_title'], 
					"chosen"           => ( in_array($optionValueData['option_type_id'], $cartOptionValueArr) ) ? true : false, 
					"amount"           => $this->getFormattedPrice($optionValueData['default_price']), 
					"vat" 			   => $this->getFormattedPrice( $this->getVatAmount( $optionValueData['default_price'], $this->getVatPercent() ) ), 
					"overrideImageUrl" => false,
					"customIdentifier" => $optionValueData['option_type_id'],
					"sortOrder"        => ( $sort_order > 0 ) ? $sort_order : 1
					);
					$last_order = $sort_order;
			} 
		}
		return !empty($attributeValues) ? $attributeValues : '';
	}
	
	/**
     * get Configurable product attributes
     *
     * @param   $product_obj
     * @return  attribute array
     */
	private function getConfigurableProductAttributes ($product_obj) {
		
		$attributes = array();
		$productConfigAttributeOptions = $product_obj->getTypeInstance(true)->getConfigurableAttributesAsArray($product_obj);

		if ( is_array($productConfigAttributeOptions) && count($productConfigAttributeOptions) > 0 ) {
			foreach ($productConfigAttributeOptions as $productConfigAttribute) {
				$attribute_code = $productConfigAttribute['attribute_code'];
				$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attribute_code);
				
				switch ( strtolower(trim($attribute_details->getData('frontend_input'))) ) {
						case 'select':
						case 'radio':
							$optionType = "RADIO";
							break;
						case 'checkbox':
							$optionType = "CHECKBOX";
							break;
						case 'field':
						case 'area':
						default:
							$optionType = "INPUT";
							break;
					}
					
					$attributes[] = array(
						"type" 			   => $optionType,
						"mandatory" 	   => $attribute_details->getData('is_require') ? true : false,
						"name" 	  		   => $attribute_details->getData('store_label'),
						"attributeValues"  => $this->getCofigAttributeValues($productConfigAttribute['values'], $product_obj),
						"customIdentifier" => $attribute_details->getData('attribute_id'),
						"sortOrder" 	   => ( $attribute_details->getData('sort_order') > 0 ) ? $attribute_details->getData('sort_order') : 1
						);
			} 
		}
		$simpleOptions = Mage::helper('core')->decorateArray($product_obj->getOptions());
		if ( is_array($simpleOptions) && count($simpleOptions) > 0 ) {
			$sortOrder = 0;
			foreach ($simpleOptions as $option) {

				$sortOrder ++;
				$optionData = $option->getData();
				
				switch ( strtolower(trim($optionData['type'])) ) {
					case 'drop_down':
					case 'radio':
						$optionType = "RADIO";
						break;
					case 'checkbox':
						$optionType = "CHECKBOX";
						break;
					case 'field':
					case 'area':
					default:
						$optionType = "INPUT";
						break;
				}
				
				$attributes[] = array(
					"type" 			  => $optionType,
					"mandatory" 	  => $optionData['is_require'] ? true : false,
					"name" 	  		  => $optionData['default_title'],
					"attributeValues" => $this->getAttributeValues($option, $product_obj),
					"customIdentifier" => $optionData['option_id'],
					"sortOrder" 	  => $sortOrder
					);
			}
		}
		return !empty($attributes) ? $attributes : array();
	}
	
	/**
     * get cofig product attributes value
     *
     * @param   $productConfigValues $product_obj
     * @return  attribute value array
     */
	private function getCofigAttributeValues ($productConfigValues, $product_obj) {
		
		$sortOrder = 1;
		$attributeValues = array();
		
		foreach ( $productConfigValues as  $value ) {
			$attributeValues[] = array(
				"value"            => $value['default_label'], 
				"chosen"           => false, 
				"amount"           => $this->getFormattedPrice($value['pricing_value']), 
				"vat" 			   => $this->getFormattedPrice( $this->getVatAmount( $value['pricing_value'], $this->getVatPercent() ) ), 
				"overrideImageUrl" => false,
				"customIdentifier" => $value['value_index'],
				"sortOrder"        => $sortOrder
				); 
			$sortOrder++;
		} 

		return !empty($attributeValues) ? $attributeValues : '';
	}
	
   /**
	* The function returns the formatted price according to the currency.
	* @return float
	*/
	
	private function getFormattedPrice ( $realPrice ) {
		$formattedPrice = number_format(Mage::helper('core')->currency($realPrice, false, false), 2, '.', '');
		
		return ($formattedPrice) ? $formattedPrice * 100: 0;
	}
	
	public function prd ($d) {
		echo "<pre>";print_r($d);exit;
	}
	
	public function pr ($d) {
		echo "<pre>";print_r($d);
	}
	
	/**
     * get request collection
     *
     * @param   $identifier
     * @return  array
     */
	private function getRequestCollection ( $identifier ) {
	   	$requestCollection = json_decode(json_encode($this->request),true);
		return (isset($requestCollection[$identifier])) ? $requestCollection[$identifier] : NULL;
		
	}
	
	
	/**
	* The function used to get methods of shipping.
	* @return Array
	*/
	private function getDeliveryMethods ( $methods, $chosenDeliveryMethodName = NULL ) {
			
		$deliveryMethodArray = array();
		$total_methods = count($methods);
		if ( is_array($methods) && count($methods) > 0 ) {
			foreach ( $methods as  $method ) { 
				$default_method_sort_order = Mage::getStoreConfig('carriers/'.$method['carrier'].'/sort_order'); 
				if ( $default_method_sort_order == 0 || empty( $default_method_sort_order ) ) {
					$order = 1;
				} else {
					$order = $default_method_sort_order;
				}
			
				if ($total_methods == 1 && $chosenDeliveryMethodName == NULL) {
					$chosen = true;
				} elseif ( $chosenDeliveryMethodName != NULL) {
					$chosen = ( trim( $chosenDeliveryMethodName ) == trim( $method['carrier_title'] ) ) ? true : false;
				} else {
					$chosen = false;
				}
				$sort_order = ( $total_methods - $order ) + 1; 
			
				$deliveryMethodArray[] = array(
					"name"             => $method['carrier_title'],  //$method['carrier']
					"description"      => $method['rate_id'],
					"amount"           => $this->getFormattedPrice($method['price']),
					"vatPercent"       => 0,
					"type"             => "OTHER",
					"vat"              => 0,
					"customIdentifier" => $method['carrier'],
					"chosen"           => $chosen,
					"sortOrder" 	   => ( $sort_order > 0 ) ? $sort_order : 1  
				);
				$order++;
			}
			
		}
		return $deliveryMethodArray;
	}
	
	/**
	* The function used to get base url of the store.
	* @return url
	*/
	private function getBaseUrl ( $store_id ) {
		$url = Mage::app()->getStore($store_id)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
		return $url;
	}
	
	/*
	 * Notify cloud server that a completed transaction is present for specified merchant
	 *
	 * @param int $merchantReference
	 */
	 public function completedOrderNotification( $merchantReference ) {
		//open connection
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://54.243.60.205/notify.php?merchantReference=$merchantReference");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		curl_close($ch);
	 }
	 
	 /*
	 * Set order message in the order detail page 
	 *
	 * @param string $order_status
	 * @return string $msg
	 */
	 private function setOrderMessage( $order_status ) {
		switch ($order_status) {
			case 'pending' : 
				$order_msg = 'Payair has set the order status to Pending as the order is awaiting authorization.';
				break;
			case 'processing' : 
				$order_msg = 'Payair has set the status to Processing. Please INVOICE or SHIP the order to clear this transaction.';
				break;
			case 'canceled' : 
				$order_msg = 'The order has been Canceled.';
				break;
			case 'holded' : 
				$order_msg = 'Some data needs to be verified, therefore this order has been placed On Hold.';
				break;
			default :
				$order_msg = '';
		}
		
		return $order_msg;
	 }
    
	/*
	 * Set state of the order 
	 *
	 * @param string $order,$order_status
	 * @return NULL
	 */
	private function setOrderState ( $order, $order_status ) {
	
		switch ( $order_status ) {
			case 'processing':
			case 'holded':
			case 'canceled':
				$order_message = $this->setOrderMessage($order_status);
				$order->setState( $order_status, $order_status, $order_message, true );
				$order->setStatus($order_status);
				$order->save();
				break;
				
			case 'pending' :
				$order_message = $this->setOrderMessage($order_status);
				$order->setState( Mage_Sales_Model_Order::STATE_NEW, Mage_Sales_Model_Order::STATE_NEW, $order_message, true);
				$order->setStatus("pending");
				$order->save();
				break;
				
			case 'complete' :
			case 'closed' : 
				try {
					if ( Mage::helper('core')->isModuleEnabled('Webkul_Marketplacepartner') ) {
						Mage::getModel('marketplacepartner/Productsales')->getProductSalesCalculation($order);
					}
					if ( !$order->canInvoice() ) {
						Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
					}
					$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
					if ( !$invoice->getTotalQty() ) {
					Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
					}
					$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
					$invoice->register();
					$transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
					$transactionSave->save();
					
					if( $order->canShip() ) {
						$itemQty =  $order->getItemsCollection()->count();
						$shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
						$shipment = new Mage_Sales_Model_Order_Shipment_Api();
						$shipmentId = $shipment->create( $order->getIncrementId() );
						$order->setState( 'complete', 'complete', 'Payair has Completed and Shipped Order automatically ', true);
						$order->addStatusHistoryComment('Payair has Completed and Shipped Order automatically.', false);
        
					}

				} catch (Mage_Core_Exception $e) {
					
				}	
				break;
			default :
				$order->setState( $order_status, $order_status, "No status seleted", true );
				$order->save();
		}
	}	
	
	private function limitStringLenght ( $string, $length ) {
		$str_strip = strip_tags($string);
		$str = substr( $str_strip, 0, $length );
		return  $str ;
	}
}
   

?>
