var Payair = Class.create();
	Payair.prototype = {
	initialize: function(){
	
		this.payment_method = $$('input:checked[type="radio"][name="payment[method]"]').pluck('value');
		this.preventDefaultClickEvent();	
	},
	
	preventDefaultClickEvent: function(ga) {
		
		if( ga != undefined)	{
			this.payment_method = ga;
		}
		
		var e = $$('div#payment-buttons-container button.button')[0];
		
		if(this.payment_method=='gate') {			
			e.setAttribute('onclick','return false;');
		}
		else {
			e.setAttribute('onclick','payment.save();');
		}
	},
	
	displayQR: function() {
		
		$$('div#payment-buttons-container button.button').invoke('observe','click',function(){
			
			selectedPaymentMethod = $$('input:checked[type="radio"][name="payment[method]"]').pluck('value');
			
			if(selectedPaymentMethod == 'gate') {
				//The choosed mathod is payair. Call the payair js code here. And can prevent the default click event
				Payair_DATA.releaseHelpActive();
				GetQRData();
				showScanInfo();
				showScanWrapper();
				return false;
			}
			else {
				// The choosed method is something else. Proceed further
				return true;
			}
		});				
	}
}
	
var QR = new Payair();
payment.addBeforeInitFunction(QR,QR.displayQR);

$('co-payment-form').on('change', '.radio', function() {
	var payment_method = $$('input:checked[type="radio"][name="payment[method]"]').pluck('value');
	QR.preventDefaultClickEvent(payment_method);

});