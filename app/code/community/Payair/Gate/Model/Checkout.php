<?php

class Payair_Gate_Model_Checkout extends Mage_Payment_Model_Method_Abstract {

    protected $_code          = 'gate';

    /**
     * Availability options
     */
    protected $_isGateway               = false;
    protected $_canOrder                = false;
    protected $_canAuthorize            = false;
    protected $_canCapture              = false;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;    
    protected $_canReviewPayment        = true;    
    protected $_canUseForMultishipping  = true;
    protected $_canCancelInvoice        = false;

    
    public function authorize(Varien_Object $payment, $amount) { 
        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
        return $this;
    }
    
    public function capture(Varien_Object $payment, $amount) { 
        parent::capture($payment, $amount);
        $data = $payment->getData();

        if(strlen($data['last_trans_id']) == 7) {
            $data['last_trans_id'] = "0".$data['last_trans_id'];
        }
        
        $string = 'clear' .
                "\t" .number_format( $amount ,2 , '','').
                "\t" . $this->getConfig('gate_marchantref') .
                "\t" . $payment->getOrder()->getEntityId() .
                "\t" . str_pad($data['last_trans_id'], 8, "0", STR_PAD_LEFT).
                "\t";
        
        $request = 'request_type=clear' .
                '&amount=' .number_format( $amount ,2 , '','').
                '&merchant_reference=' . $this->getConfig('gate_marchantref') .
                '&customer_refno=' . $payment->getOrder()->getEntityId() .
                '&payair_reference=' . str_pad($data['last_trans_id'], 8, "0", STR_PAD_LEFT) .
                '&date=' . date("Ymd") .
                '&mac=' . $this->mac($string);
        
        $result = $this->sendRequest($request);                   
		
		/**
		 *Create Reciept Call
		 */        
        
        $all_items = $payment->getOrder()->getAllItems();
        
        $i = 0;
        $total_tax = '';
        $cart_items = '';
        $cart_items_serial = '';
        
        foreach ($all_items as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $options = $item->getProductOptions();
            if ($item->getProductType() == 'configurable'){
                $options['options'] = $options['attributes_info'];
            }
            
            $j = 1;
            $attr = '';
            if (count(@$options['options']) > 0){
                foreach ($options['options'] as $key => $o) {
                        $attr[] = $o['value'] ;
                }
                $attr = join('/', $attr);
            }                            

            $tax = $item->getTaxPercent();
            $total_tax += $item->getTaxAmount();
            
            $taxRate = $tax / 100;
            $tax_amount = $item->getPrice() * $taxRate;

            $productPriceInclTax = $item->getPrice() + $tax_amount;
                $cart_items .= '&post' . $i .'='. 
                        'price=' . number_format($productPriceInclTax, 2, '', '') .
                        ($attr != null?';extra=' . $attr :'') .
                        ';name=' . urlencode($item->getName()) .
                        ';vat=' . (int)number_format($tax_amount, 2, '', '') ;


                $cart_items_serial .=  number_format($productPriceInclTax, 2, '', '') .
                        ($attr != null?"\t". $attr :'').
                        "\t". $item->getName() .
                        "\t" . (int)number_format($tax_amount, 2, '', '')."\t" ;

            $i++;
        }   
            $cart_items .= '&post' . $i .'='. 
                    'price=' . (int)number_format($payment->getOrder()->getShippingAmount(), 2, '', '') .
                    ';name=' . 'Shipping' .
                    ';vat=' . 0 ;


            $cart_items_serial .=  (int)number_format($payment->getOrder()->getShippingAmount(), 2, '', '') .
                    "\t". 'Shipping' .
                    "\t" . 0 ."\t" ;
        
        
        $string = 'create_receipt' .
                "\t" . $this->getConfig('gate_marchantref') .
                "\t" . $payment->getOrder()->getEntityId() .
                "\t" . str_pad($data['last_trans_id'], 8, "0", STR_PAD_LEFT) .
                "\t" . $this->getConfig('gate_final_info_top') .
                "\t" . $this->getConfig('gate_final_info_bottom') .
                "\t" . $payment->getOrder()->getCustomerEmail() .
                "\t" . number_format( $amount ,2 , '','') .
                "\t" . number_format($total_tax , 2, '', '') .
                "\t" . ($i + 1) ."\t".
                $cart_items_serial ;
                             
        $request = 'request_type=create_receipt' .
                '&merchant_reference=' . $this->getConfig('gate_marchantref') .
                '&customer_refno=' . $payment->getOrder()->getEntityId() .
                '&payair_reference=' . str_pad($data['last_trans_id'], 8, "0", STR_PAD_LEFT) .
                '&top_information=' . $this->getConfig('gate_final_info_top') .
                '&bottom_information=' . $this->getConfig('gate_final_info_bottom') .
                '&user_email=' . $payment->getOrder()->getCustomerEmail() .
                '&amount=' . number_format( $amount ,2 , '','') .
                '&total_vat=' . number_format($total_tax , 2, '', '') .
                '&no_of_items=' . ($i + 1) .
                $cart_items .
                '&mac=' . $this->mac($string);
        
        $result = $this->sendRequest($request);    
       
        if(!preg_match('!status=OK!is', $result)){
            $error = Mage::helper('paygate')->__('Error in create reciept');

            if ($error !== false) {
            Mage::throwException($error);
            }
        }

        $payment->setForcedState(Mage_Sales_Model_Order_Invoice::STATE_PAID);

        $payment->setStatus(self::STATUS_APPROVED);            
        
        return $this;
    }

    public function cancel(Varien_Object $payment) {
        parent::cancel($payment);
        
        return $this;
    }
    
    public function refund(Varien_Object $payment, $amount) {
        parent::refund($payment, $amount);
        $data = $payment->getData();

        $string = 'credit'."\t".
                number_format($data['amount_paid'], 2, '', '')."\t".
                Mage::getStoreConfig('payment/gate/gate_marchantref')."\t".
                $data['increment_id']."\t".
                str_pad(str_ireplace('-capture', '', $data['refund_transaction_id']), 8, "0", STR_PAD_LEFT)."\t";
        
        $url = 'https://test.payair.com:10000/merchant_ri/';
        $data = 'request_type=credit' .
                '&amount='.number_format($data['amount_paid'], 2, '', '').             
                '&merchant_reference='.Mage::getStoreConfig('payment/gate/gate_marchantref').
                '&customer_refno='.$data['increment_id'].
                '&payair_reference='.str_pad(str_ireplace('-capture', '', $data['refund_transaction_id']), 8, "0", STR_PAD_LEFT).
                '&mac='.$this->mac($string);
                 
		//open connection
        $ch = curl_init();

		//set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		//execute post
        $result = curl_exec($ch);

		//close connection
        curl_close($ch);
        
        if (preg_match('!error!is', $result)) {
            $error = Mage::helper('paygate')->__('Error in refunding the payment');
            if ($error !== false) {
				Mage::throwException($error);
            }
        }
        
        $data = $payment->getData();
        $all_items = $payment->getOrder()->getAllItems();
        
      
        $i = 0;
        $i = 0;
        foreach ($all_items as $item) {
            
            if ($item->getParentItemId()) {
                continue;
            }            
            
            $options = $item->getProductOptions();

            $j = 1;
            $attr = '';
            foreach ($options['options'] as $key => $o) {
                    $attr[] = $o['value'] ;                
            }
            $attr = join('/', $attr);

            $tax = $item->getTaxPercent();
            $total_tax += $item->getTaxAmount();

            
            $taxRate = $tax / 100;
            $tax_amount = $item->getPrice() * $taxRate;
            if ($tax_amount > 0) {
                $tax_amount = (int)number_format($tax_amount, 2, '', '');
            }
            if ($total_tax > 0) {
                $total_tax = (int)number_format($total_tax, 2, '', '');
            }
            $productPriceInclTax = $item->getPrice() + $tax_amount;
            
            $cart_items .= '&post' . $i .'='. 
                    'price=' . number_format($productPriceInclTax, 2, '', '') .
                    ($attr != null?';extra=' . $attr :'') .
                    ';name=' . urlencode($item->getName()) .
                    ';vat=' . $tax_amount ;


            $cart_items_serial .=  number_format($productPriceInclTax, 2, '', '') .
                    ($attr != null?"\t". $attr :'').
                    "\t". $item->getName() .
                    "\t" . $tax_amount."\t" ;

            $i++;
        }         
        
            $cart_items .= '&post' . $i .'='. 
                    'price=' . (int)number_format($payment->getOrder()->getShippingAmount(), 2, '', '') .
                    ';name=' . 'Shipping' .
                    ';vat=' . 0 ;


            $cart_items_serial .=  (int)number_format($payment->getOrder()->getShippingAmount(), 2, '', '') .
                    "\t". 'Shipping' .
                    "\t" . 0 ."\t" ;        
        
        $string = 'create_receipt' .
                "\t" . $this->getConfig('gate_marchantref') .
                "\t" . $payment->getOrder()->getEntityId() .
                "\t" . str_pad(str_ireplace("-capture",'',$data['last_trans_id']), 8, "0", STR_PAD_LEFT) .
                "\t" . $this->getConfig('gate_refund_info_top') .
                "\t" . $this->getConfig('gate_refund_info_bottom') .
                "\t" . $payment->getOrder()->getCustomerEmail() .
                "\t" . "-".number_format( $amount ,2 , '','') .
                "\t" . $total_tax.
                "\t" . ($i + 1) ."\t".
                $cart_items_serial ;
        
        $request = 'request_type=create_receipt' .
                '&merchant_reference=' . $this->getConfig('gate_marchantref') .
                '&customer_refno=' . $payment->getOrder()->getEntityId() .
                '&payair_reference=' . str_pad(str_ireplace("-capture",'',$data['last_trans_id']), 8, "0", STR_PAD_LEFT) .
                '&top_information=' . $this->getConfig('gate_refund_info_top') .
                '&bottom_information=' . $this->getConfig('gate_refund_info_bottom') .
                '&user_email=' . $payment->getOrder()->getCustomerEmail() .
                '&amount=' . "-".number_format( $amount ,2 , '','') .
                '&total_vat=' . $total_tax .
                '&no_of_items=' . ($i + 1) .
                $cart_items .
                '&mac=' . $this->mac($string);
        
        $result = $this->sendRequest($request);                 
        
        return $this;
    }
    
    public function mac($string) {
        $hash = $string . Mage::getStoreConfig('payment/gate/gate_secret');

        return hash('sha256', $hash);
    }
    
    protected function getConfig($item){
        return Mage::getStoreConfig('payment/gate/'.$item);
    }    
    
    protected function sendRequest($data){
        //open connection
        $ch = curl_init();
        
        $headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 

		//set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $this->getConfig('gate_merchant_url'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		//execute post
        $result = curl_exec($ch);

		//close connection
        curl_close($ch);
        
        return $result;
    }
}

?>
