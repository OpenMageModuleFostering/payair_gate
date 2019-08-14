<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class Payair_Gate_Model_Order_Api extends Mage_Sales_Model_Order_Api {

   public function cancel($orderIncrementId) {
        if ($order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId)) {
	
            try {
                $response = $this->callRefundAction($order);

                if ($response != 'A0') {
                    //$this->_getSession()->addError('Order Cancellation Failed, because refunding of amount failed. Payair Error : ' . $response);
                    $order->setState($order->getState(), $order->getState(), "Order Cancellation Failed, because refunding of amount failed. Payair Error: " . $response);
                    $order->save();
                } else {

                    $order->cancel()
                            ->save();
                    $this->_getSession()->addSuccess(
                            $this->__('The order has been cancelled.')
                    );
                   // $this->_getSession()->addSuccess('Amount has been successfully refunded by Payair');
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, Mage_Sales_Model_Order::STATE_CANCELED, "Amount has been successfully refunded by Payair");
                    $order->save();
                }
            } catch (Mage_Core_Exception $e) {
               // $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
               // $this->_getSession()->addError($this->__('The order has not been cancelled.'));
               // Mage::logException($e);
            }
        
        }
		
		return true; //$response;
    }

    public function callRefundAction($order) {
        //Mage::log('Method Module called : Payair_Adminhtml_Sales_OrderController :: callRefundAction() ');
        try {
            $storeid = $order->getStoreId(); // FROM STORE ID for purchase store

            $merchant_reference = $this->getConfig('gate_marchantref', $storeid);

            $customer_refno = $order->getIncrementId(); // FROM ORDER ID
            $amount = $this->getFormattedPrice($order->getBaseGrandTotal()); // getting integer value for BaseGrandTotal
            $payair_reference = $order->getPayment()->getLastTransId();  // FROM TRANSACTION ID

            $string = 'credit' . "\t" . $amount . "\t" . $merchant_reference . "\t" . $customer_refno . "\t" . $payair_reference . "\t";

            $mac = $this->mac($string, $storeid);
            $data = 'request_type=credit&amount=' . $amount . '&merchant_reference=' . $merchant_reference . '&customer_refno=' . $customer_refno . '&payair_reference=' . $payair_reference . '&mac=' . $mac;
            $response = $this->sendRequest($data, $storeid);
            $responseArry = explode('&', $response);
            $tempArryStatus = explode('=', $responseArry[0]);
            $tempArryMac = explode('=', $responseArry[4]);
            $payairStatus = $tempArryStatus[1];
            $payairMac = $tempArryMac[1];
            /* Cross refer MAC match  disbled till further notice */
            /* if($payairMac != $mac )
              {
              return 'Wrong MAC';
              }else{
              return $payairStatus;
              } */
            return $payairStatus;
            
        } catch (Exception $e) {
           // Mage::logException($e);
        }
    }

    public function sendRequest($data, $storeid) {
		Mage::log("\nRefund Request: \n" . print_r($data, TRUE), null, 'refund_log.txt');
	    //open connection
        $ch = curl_init();

        $headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $this->getConfig('gate_merchant_url', $storeid));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //execute post
        $result = curl_exec($ch);
		Mage::log("\nRefund Response: \n" . print_r($result, TRUE), null, 'refund_log.txt');
		//close connection
        curl_close($ch);
        return $result;
    }

    public function mac($string, $storeid) {
        $hash = $string . $this->getConfig('gate_secret', $storeid);
        return hash('sha256', $hash);
    }

    public function getConfig($item, $storeid) {
        return Mage::getStoreConfig('payment/gate/' . $item, $storeid);
    }

    public function getFormattedPrice($realPrice) {
        $formattedPrice = number_format(Mage::helper('core')->currency($realPrice, false, false), 2, '.', '');
        return ($formattedPrice) ? $formattedPrice * 100 : 0;
    }

}