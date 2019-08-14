
function getPayairLocation() {
	var url = 'https://payair.com/ms/';
	var src = document.getElementById('Payair_Location').src;

	var locals = [/localhost/, /10\.[0|2]\.[0-9]{1,3}\.[0-9]{1,3}/, /\.\.\//];

	for (var i = 0; i < locals.length; i++) {
		if (src.match(locals[i])) {
			var uri = window.location;
			url = uri.protocol + '//' + uri.host + '/ms/';
		}
	}

	var matches = src.match(/(test|dev|qa)(?=\.payair\.com\/embed\/)/);
	if (matches && matches.length > 0) {
		url = 'https://' + matches[0] + '.payair.com/embed/';
	}

	return url;
}

function Init()
{

	var userAgent = detectUserAgent();

	// Create a Payair wrap which shall contain all Payair info:
	createPayairProperties();
	
	// Init scanbox vars:
	var boxDims = getScanboxWidthHeight();
	var scanboxWidth = boxDims[0];
	var scanboxHeight = boxDims[1];
	
	// Draw Scan infobox and wrapper:
	drawScanInfo(scanboxWidth, scanboxHeight);
	drawScanWrapper(userAgent);
	drawTestIframe();
	
	// Draw the Payair button on the ID that was returned:
	drawPayairButton();

}

function getScanboxWidthHeight()
{
	var scanboxWidth = 480;
	var scanboxHeight = 340;
	return new Array(scanboxWidth, scanboxHeight);
}

function drawTestIframe() {
	var parent = document.body;
	var child = document.createElement('iframe');
	child.id="payairTestIframe-" + Payair_DATA.articleID;
	child.target='top';
	child.src='about:blank';
	child.style.display = 'none';
	parent.appendChild(child);
}


function drawScanInfo(width, height)
{

	var userAgent = detectUserAgent();
	// Get document width and height:
	var dimensions = getViewPort();
	
	// Draw the scaninfoWRAP at the specified coordinates with MAX width and height:
	var childGuest = document.createElement("div"); 
	childGuest.id = "Payair_scanbox-" + Payair_DATA.articleID;
	//parentGuest.parentNode.insertBefore(childGuest, parentGuest.nextSibling);
	document.body.appendChild(childGuest);
	
	var e = document.getElementById('Payair_scanbox-' + Payair_DATA.articleID);
	
	// Set styles for the Payair scanbox:
	e.style.display = 'none';
	e.style.zIndex = -1;
	e.style.width = width+'px';
	e.style.height = height+'px';
	
	if ((userAgent == 'internet explorer') && (document.documentMode < 7))
	{
		e.style.position = 'absolute';
	}
	else e.style.position = 'fixed';
	
	// Draw the contents for the scanbox:
	drawScanboxContent(width, height);
	
	// Get the positioning for the scanbox and place it:
	var xy = getScanboxXY(dimensions, width, height);
	placeObjectboxXY(xy, document.getElementById('Payair_scanbox-' + Payair_DATA.articleID));
	
}

function drawScanboxContent(width, height)
{
	var closeTopMargin = -10;
	var closeRightMargin = -10;
	var Payair_main_location = Payair_DATA.getPayairLocation();

	var e = document.getElementById('Payair_scanbox-' + Payair_DATA.articleID);
	e.innerHTML = '<div onClick="hideScanWrapper('+Payair_DATA.articleID+'); hideScanBox('+Payair_DATA.articleID+'); return false;" id="Payair_close_iFrame-'+ Payair_DATA.articleID+'" style="position: absolute; top: '+closeTopMargin+'px; width: 25px; height: 25px; right: '+closeRightMargin+'px; z-index: 25000;"><img src="'+Payair_main_location+'img/close_button.png" onMouseOver="this.style.opacity = 0.75;" onmouseout="this.style.opacity = 1;" style="cursor: pointer; border: 0px;"></div><iframe id="Payair_iFrame-'+Payair_DATA.articleID+'" allowTransparency="true" name="Payair_iFrame-'+Payair_DATA.articleID+'" style="overflow:hidden; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.50); -moz-border-radius: 10px; -webkit-border-radius: 10px; -moz-box-shadow: 0 10px 40px rgba(0,0,0,0.50); -webkit-box-shadow: 0 10px 40px rgba(0,0,0,0.50); border: 0" width="480" height="340" scrolling="no"></iframe>';
}

function placeObjectboxXY(xy, e)
{

	// Make y HALF of the height IF not IE QUIRKS:
	xy[1] = Math.floor(xy[1] / 2);
	
	e.style.left = xy[0]+'px';
	e.style.top = xy[1]+'px';
}

function resizeWrapper()
{
	setScanWrapXY(document.getElementById('Payair_scanwrap-' + Payair_DATA.articleID));
	setScanWrapXY(document.getElementById('Payair_scanwrap_clickable-' + Payair_DATA.articleID));
}

function moveWrapper()
{
	var userAgent = detectUserAgent();
	if ((userAgent == 'internet explorer') && (document.documentMode < 7))
	{
		var e = document.getElementById('Payair_scanwrap-' + Payair_DATA.articleID);
		e.style.top = document.body.scrollTop;
		e.style.left = document.body.scrollLeft;
		
		e = document.getElementById('Payair_scanwrap_clickable-' + Payair_DATA.articleID);
		e.style.top = document.body.scrollTop;
		e.style.left = document.body.scrollLeft;
		
	}
}

function getScanboxXY(dimensions, width, height)
{
	var ret = [];

	var widthOffset;
	var heightOffset;
	var userAgent = detectUserAgent();
	
	if ((userAgent == 'internet explorer') && (document.documentMode < 7))
	{
		var scrolledTop = document.body.scrollTop;
		widthOffset = Math.floor((dimensions[0] - width) / 2);
		heightOffset = (scrolledTop + Math.floor(((dimensions[1] - height) / 1.5))) * 2;
	}
	else
	{
		widthOffset = Math.floor(((dimensions[0] - width) / 2));
		heightOffset = Math.floor(((dimensions[1] - height) / 1.5));
	}

	ret.push(widthOffset);
	ret.push(heightOffset);
	return ret;
	
}

function drawScanWrapper(userAgent)
{
	// Get document width and height:
	var dimensions = getDocDimensions();
	
	// Draw the scaninfoWRAP at the specified coordinates with MAX width and height:
	var childGuest = document.createElement("div"); 
	childGuest.id = "Payair_scanwrap-" + Payair_DATA.articleID;
	//parentGuest.parentNode.insertBefore(childGuest, parentGuest.nextSibling);
	document.body.appendChild(childGuest);
	
	var e = document.getElementById(childGuest.id);
	
	// Set some styles to the scan wrapper:
	e.style.display = 'none';
	e.style.position = 'fixed';
	e.style.top = '0';
	e.style.left = '0';
	e.style.right = '0';
	e.style.bottom = '0';
	
	if ((userAgent == 'internet explorer') && (document.documentMode < 7))
	{
		e.style.width = dimensions[0];
		e.style.height = dimensions[1];
	}
	
	// Add the element that responds to a click inside the scanwrap:
	e.innerHTML = '<div id="Payair_scanwrap_clickable-'+Payair_DATA.articleID+'" onClick="hideScanWrapper('+Payair_DATA.articleID+'); hideScanBox('+Payair_DATA.articleID+'); return false;" style="top: 0; left: 0; right: 0; bottom: 0"></div>';
	
	// Set some styles to it:
	e = document.getElementById('Payair_scanwrap_clickable-' + Payair_DATA.articleID);
	e.style.display = 'none';
	e.style.backgroundColor = '#000';
	e.style.position = 'fixed';
	e.style.top = '0';
	e.style.left = '0';
	e.style.right = '0';
	e.style.bottom = '0';
	
	// Alpha transparency?
	if (userAgent == 'internet explorer')
	{
		e.style.filter ='progid:DXImageTransform.Microsoft.Alpha(Opacity=80)';
		if (document.documentMode < 7)
		{
			e.style.width = dimensions[0];
			e.style.height = dimensions[1];
			e.style.position = 'absolute';
		}
	}
	else
	{
		e.style.filter = 'alpha(opacity=0.1)';
		e.style.opacity = 0.7;
	}
	
	setScanWrapXY(document.getElementById('Payair_scanwrap-' + Payair_DATA.articleID));
	
}

function setScanWrapXY(e)
{
	var dimensions = getViewPort();
	e.style.width = dimensions[0]+'px';
	e.style.height = dimensions[1]+'px';
}

function startIframe(paramstring, articleID)
{
	var e = document.getElementById('Payair_iFrame-' + articleID);
	var Payair_main_location = Payair_DATA.getPayairLocation();
	e.src=Payair_main_location+'index.html?'+paramstring;
}

function setParamStringForIframe(merchantreference, articleID, product_name, product_price, product_currency)
{
	return 'type=product&merchant_reference='+merchantreference+'&article_id='+articleID+'&product_name='+product_name+'&product_price='+product_price+'&product_currency='+product_currency;
}

function showScanInfo(articleID)
{
	// Set box to slighty more than wrapper:
	var e = document.getElementById('Payair_scanbox-' + articleID);
	e.style.zIndex = 20000;
	e.style.display = 'inline';
	
	var flashnodes = Payair_DATA.toArray(document.getElementsByTagName('object'));
	
	var loop = 0;
	while (loop < flashnodes.length)
	{
		flashnodes[loop].style.visibility = 'hidden';
		loop++;
	}
	
}

function showScanWrapper(articleID)
{
	// Set Wrapper zIndex to slightly less than actual box:
	var e = document.getElementById('Payair_scanwrap-' + articleID);
	e.style.zIndex = 9000;
	e.style.display = 'inline';
	
	e = document.getElementById('Payair_scanwrap_clickable-' + articleID);
	
	e.style.zIndex = 9000;
	e.style.display = 'inline';
	
	e = document.getElementById('Payair_button-' + articleID);
	e.style.visibility = 'hidden';
	
}

function hideScanWrapper(articleID)
{
		
	var e = document.getElementById('Payair_scanwrap-' + articleID);
	e.style.display = 'none';
		
	e = document.getElementById('Payair_button-' + articleID);
	e.style.visibility = 'visible';
}

function hideScanBox(articleID)
{
	var e = document.getElementById('Payair_scanbox-' + articleID);
	e.style.display = 'none';
	e.style.zIndex = -1;
	
	var flashnodes = Payair_DATA.toArray(document.getElementsByTagName('object'));
	
	var loop = 0;
	while (loop < flashnodes.length)
	{
		flashnodes[loop].style.visibility = 'visible';
		loop++;
	}
	
}

function createPayairProperties()
{
	var parentGuest = document.getElementById("Payair_QR-"+Payair_DATA.articleID);
	var childGuest = document.createElement("div"); 
	childGuest.id = "Payair_mainwrap-"+Payair_DATA.articleID;
	parentGuest.parentNode.insertBefore(childGuest, parentGuest.nextSibling);
	
	return childGuest.id;
	
}

function drawPayairButton()
{

	// Create a button and make scanwrap its parent:
	var parentGuest = document.getElementById("Payair_mainwrap-" + Payair_DATA.articleID);
	var childGuest = document.createElement("div"); 
	childGuest.id = "Payair_button-" + Payair_DATA.articleID;
	parentGuest.parentNode.insertBefore(childGuest, parentGuest.nextSibling);

	// Create a reference to the mainID:
	var target = childGuest.id;
	// Add contents to string:
	var text = '';
	
	if (detectMobileDevice() == false)
	{
		text = '<img onMouseOver="this.style.opacity = 0.75;" onmouseout="this.style.opacity = 1;" src="' + getPayairLocation() + 'img/payair_button.png" onClick="Payair_DATA.releaseHelpActive(); startProductPopup('+Payair_DATA.articleID+'); showScanInfo('+Payair_DATA.articleID+'); showScanWrapper('+Payair_DATA.articleID+'); return false;" style="cursor: pointer;" />';
	}
	else
	{
		text = '<img onMouseOver="this.style.opacity = 0.75;" onmouseout="this.style.opacity = 1;" src="' + getPayairLocation() + 'img/payair_button.png" onClick="redirectByProduct('+Payair_DATA.articleID+'); return false;" style="cursor: pointer;">';
	}
	// Apply string to target:
	document.getElementById(target).innerHTML=text;
	
	var e = document.getElementById('Payair_button-' + Payair_DATA.articleID);
	e.style.display = 'inline';
	e.style.zIndex = 10000;
	
}

function getStoreFallback() {

	var fallbackUrl;

	if ((detectMobileDevice() == 'iphone') || (detectMobileDevice() == 'ipad')) fallbackUrl = 'http://itunes.apple.com/se/app/payair/id411434027?mt=8';
	else if (detectMobileDevice() == 'android') fallbackUrl = 'https://market.android.com/details?id=com.most.android';

	return fallbackUrl;
}

function redirectByProduct(articleID) {

	var varStatus = Payair_DATA.getVarStatus(articleID);
	var v_id;
	
	if (varStatus[0] === '0') {
		v_id = '1_';
		varStatus[0] = '';
	}
	else v_id = '1_02';
	
	var URL = 'payair://'+v_id+''+varStatus[0]+''+varStatus[1];
	
	var MARKET = "https://market.android.com/details?id=com.most.android";
	var ITUNES = "http://itunes.apple.com/se/app/payair/id411434027?mt=8";

	if (navigator.userAgent.match(/Android/)) {

		if (navigator.userAgent.match(/Chrome/)) {
		// Jelly Bean with Chrome browser
			setTimeout(function() {
				if (!document.webkitHidden)
					window.location = MARKET;
			}, 1000);

			window.location = URL;
		} else {
		// Older Android browser
			var iframe = document.createElement("iframe");
			iframe.style.border = "none";
			iframe.style.width = "1px";
			iframe.style.height = "1px";
		   
			if (navigator.userAgent.match(/HTC/)) {
			// HTC 
				var e = (new Date()).getTime(); 
				setTimeout(function () {
					var a = (new Date()).getTime();
					if ((a - e) < 1000) {
						setTimeout(function() { document.location.href = MARKET; }, 0);
					}
				}, 500);
			
			} else {
			// Other
				var t = setTimeout(function() {
					window.location = MARKET;
				}, 1000);
		    
				if (window.attachEvent) window.attachEvent('onload', clearTimeout(t));
				else window.addEventListener('load', function() {
					clearTimeout(t);
				}, false);
			}
		    
			iframe.src = URL;
			document.body.appendChild(iframe);  
		}

	} else if (navigator.userAgent.match(/iPhone|iPad|iPod/)) {
	// IOS
	
		setTimeout(function() {
			if (!document.webkitHidden) {
				window.location = ITUNES;
			}
		}, 25);

		window.location = URL;
	}
	else {
		// No matching device, try launch:
		document.location.href = URL;
	}
}

function getEmbedVars(vars)
{
	// Set local vars:
	var merchantreference = false;
	var articleID = false;
	
	var product_price = false;
	var product_name = false;
	var product_currency = false;
	var payairFailure = false;
	var errormsgs = [];
	
	/*
	//script gets the src attribute based on ID of page's script element:
	var requestURL = document.getElementById("Payair_QR").getAttribute("src");

	//next use substring() to get querystring part of src
	var queryString = requestURL.substring(requestURL.indexOf("?") + 1, requestURL.length);

	//Next split the querystring into array
	var params = queryString.split("&");
	
	var payairFailure = false;
	
	
	//Next loop through params
	for(var i = 0; i < params.length; i++)
	{
		var value = params[i].substring(params[i].indexOf("=") + 1, params[i].length);
		
		params[i] = params[i].replace(value, "'" + value + "'");
		
		eval(params[i]);
	}
	*/

	// A long segment of if statements regarding if the variables sent with the embed code is valid and set:
	if (vars.product_price === false)
	{
		errormsgs.push('Parameter/variable "product_price" was not set, contained illegal characters, or was not numeric. Please specify numbers only without blankspaces. (Example: checkout_price=599)');
		payairFailure = true;
	}
	else Payair_DATA.setProductPrice(vars.product_price);
	
	if (vars.product_name == false)
	{
		errormsgs.push('Parameter/variable "product_name" was not set or contained illegal characters. Please specify a valid product_name. (Example: product_name=LED TV 42 Inches)');
		payairFailure = true;
	}
	else Payair_DATA.setProductName(vars.product_name);
	
	if (vars.product_currency == false)
	{
		errormsgs.push('Parameter/variable "product_currency" was not set or contained illegal characters. Please specify a valid product_currency. (Example: product_currency=SEK)');
		payairFailure = true;
	}
	else Payair_DATA.setProductCurrency(vars.product_currency);

	if (vars.merchantreference === false)
	{
		errormsgs.push('Parameter/variable "merchantreference" was not set or contained illegal characters. Please specify a valid merchantreference. (Example: merchantreference=100019)');
		payairFailure = true;
	}
	else Payair_DATA.setMerchantReference(vars.merchantreference);
	
	if (vars.articleID == false)
	{
		errormsgs.push('Parameter/variable "articleID" was not set or contained illegal characters. Please specify a valid merchantreference. (Example: articleID=373-BB-33)');
		payairFailure = true;
	}
	else Payair_DATA.setArticleID(vars.articleID);
	
	if (payairFailure == true)
	{
		logErrorsToConsole(errormsgs);
		return false;
	}
	Payair_DATA.setVarStatus();
	return true;
	
}

function logErrorsToConsole(msgs)
{
	
	// Function for logging errors to console:
	console.log('One or more errors found, dumping error data:');
	
	var loop = 0;
	// Go through the loop and log the errors that are sent with the array:
	while (loop < msgs.length)
	{
		console.log('Error: '+msgs[loop]);
		loop++;
	}
	
	console.log('- Please refer to this documentation for further assistance: http://www.payair.com/dev/eng/qr_implementation.php');
	console.log('- - End of Payair error messages, exiting..');
}

function IsNumeric(strString)
//  check for valid numeric strings	
{
	var strValidChars = "0123456789.-";
	var strChar;
	var blnResult = true;

	if (strString.length == 0) return false;

	//  test strString consists of valid characters listed above
	for (var i = 0; i < strString.length && blnResult == true; i++)
	{
		strChar = strString.charAt(i);
		if (strValidChars.indexOf(strChar) == -1)
		{
			blnResult = false;
		}
	}
	return blnResult;
}

function startIframeInfoOnly()
{
	Payair_DATA.setHelpActive();
	
	var e = document.getElementById('Payair_iFrame-' + Payair_DATA.articleID);
	var Payair_main_location = Payair_DATA.getPayairLocation();
	e.src=Payair_main_location+'index.html?page=help';
	showScanInfo(Payair_DATA.articleID);
	showScanWrapper(Payair_DATA.articleID);
}

function getViewPort()
{
	// ALWAYS GET THE VIEWPORT IF WINDOW WOULD HAVE BEEN RESIZED:
	
	 var viewportwidth;
	 var viewportheight;
	 
	 // the more standards compliant browsers (mozilla/netscape/opera/IE7) use window.innerWidth and window.innerHeight
	 
	 if (typeof window.innerWidth != 'undefined')
	 {
		  viewportwidth = window.innerWidth;
		  viewportheight = window.innerHeight;
	 }
	 
	// IE6 in standards compliant mode (i.e. with a valid doctype as the first line in the document)

	 else if (typeof document.documentElement != 'undefined'
		 && typeof document.documentElement.clientWidth !=
		 'undefined' && document.documentElement.clientWidth != 0)
	 {
		   viewportwidth = document.documentElement.clientWidth;
		   viewportheight = document.documentElement.clientHeight;
	 }
	 
	 // older versions of IE
	 
	 else
	 {
		   viewportwidth = document.getElementsByTagName('body')[0].clientWidth;
		   viewportheight = document.getElementsByTagName('body')[0].clientHeight;
	 }
	 
	 // Return an array with the width and height of the viewport:
	 return [viewportwidth, viewportheight];
}

function getDocDimensions() 
{
    var D = document;
	
    var maxwidth = Math.max(
        Math.max(D.body.scrollWidth, D.documentElement.scrollWidth),
        Math.max(D.body.offsetWidth, D.documentElement.offsetWidth),
        Math.max(D.body.clientWidth, D.documentElement.clientWidth)
    );
	var maxheight = Math.max(
        Math.max(D.body.scrollHeight, D.documentElement.scrollHeight),
        Math.max(D.body.offsetHeight, D.documentElement.offsetHeight),
        Math.max(D.body.clientHeight, D.documentElement.clientHeight)
    );
	return [maxwidth, maxheight];
}

function prepareScanboxReposition()
{

	var dimensions;
	// Get the width and height of document:
	dimensions = getViewPort();
	
	// Get and Set Scanbox Position:
	var boxDims = getScanboxWidthHeight();
	
	// Set width and height from result:
	var width = boxDims[0];
	var height = boxDims[1];
	
	var xy = getScanboxXY(dimensions, width, height);
	placeObjectboxXY(xy,document.getElementById('Payair_scanbox-' + Payair_DATA.articleID));
	
}

function detectUserAgent()
{
	var browser = navigator.userAgent.toLowerCase();
	var appname = navigator.appName.toLowerCase();
	
	var useragents = ['internet explorer', 'opera', 'firefox', 'chrome'];
	var loop = 0;
	while (loop < useragents.length)
	{
		
		// If a mobile device browser is found, set the agentfound to true:
		if ((browser.indexOf(useragents[loop]) != -1) || (appname.indexOf(useragents[loop]) != -1))
		{
			var userAgent = useragents[loop];
			break;
		}
		loop++;
	}
	
	return userAgent;
	
}

function detectMobileDevice()
{

	// Set the possible user agents for cellphones:
	var useragents = ['ipod', 'iphone', 'ipad', 'android', 'windows phone'];
	
	var loop = 0;
	
	// Mobile device OS is NOT found by default:
	var agentfound = false;
	var browser = navigator.userAgent.toLowerCase();
	
	// Go through the loop for useragent checks:
	while (loop < useragents.length)
	{
		
		// If a mobile device browser is found, set the agentfound to true:
		if (browser.indexOf(useragents[loop]) != -1)
		{
			agentfound = useragents[loop];
			break;
		}
		loop++;
	}
	
	// Return true or false depending if mobile device was found or not:
	return agentfound;
}

function WindowResized()
{
	prepareScanboxReposition();
	resizeWrapper();
}

function WindowScrolled()
{
	prepareScanboxReposition();
	moveWrapper();
}
function po (d) {
	var o = '';
	for (i in d) {
		o += i + " : " + d[i] + "\n"; 
	}
	alert(o);
}
function startProductPopup(articleID)
{
	var varStatus = Payair_DATA.getVarStatus(articleID);
	var paramstring = setParamStringForIframe(varStatus[0], varStatus[1], varStatus[2], varStatus[3], varStatus[4]);
	
	startIframe(paramstring, articleID);

}

function onReadyInit()
{
	//if (payair_vars != false)
	//{
		//Init();
		
	//}
}


Payair_DATA = {

	// Checkout:
	articleID: false,
	product_name: false,
	product_price: false,
	product_currency: false,
	merchantreference: false,
	help_active: true,
	payair_location: getPayairLocation(),
	
	setArticleID: function(input)
	{
		this.articleID = input
	},
	
	setProductPrice: function(input)
	{
		this.product_price = input
	},
	
	setProductName: function(input)
	{
		this.product_name = input
	},
	
	setProductCurrency: function(input)
	{
		this.product_currency = input
	},
	
	setMerchantReference: function(input)
	{
		this.merchantreference = input
	},
	
	setHelpActive: function()
	{
		this.help_active = true
	},
	
	releaseHelpActive: function()
	{
		this.help_active = false;
	},
	
	getVarStatus: function(articalId)
	{
		//return new Array(this.merchantreference, this.articleID, this.product_name, this.product_price, this.product_currency);
		return this.collection_varstatus[articalId];
	},
	setVarStatus: function () {
		if (this.collection_varstatus===undefined) {
			this.collection_varstatus = Array();
			this.collection_varstatus[this.articleID] = new Array(this.merchantreference, this.articleID, this.product_name, this.product_price, this.product_currency);
		} else {
			this.collection_varstatus[this.articleID] = new Array(this.merchantreference, this.articleID, this.product_name, this.product_price, this.product_currency);
		}
	},
	getPayairLocation: function()
	{
		return this.payair_location;
	},
	toArray: function (collection) {
	   var arr = [];

	   for (var i = collection.length >>> 0; i--;) {
		arr[i] = collection[i];
	   }

	   return arr;
	}
};

//var payair_vars = getEmbedVars(Payair_DATA);

onReadyInit();

function bindReady(handler){
    var called = false;
    function ready() {
        if (called) return;
        called = true;
        handler();
    }     
    if ( document.addEventListener ) {
        document.addEventListener( "DOMContentLoaded", function(){
            ready()
        }, false )
    } else if ( document.attachEvent ) { 
        if ( document.documentElement.doScroll && window == window.top ) {
            function tryScroll(){
                if (called) return;
                if (!document.body) return;
                try {
                    document.documentElement.doScroll("left");
                    ready()
                } catch(e) {
                    setTimeout(tryScroll, 0)
                }
            }
            tryScroll();
        }
        document.attachEvent("onreadystatechange", function(){
            if ( document.readyState === "complete" ) {
                ready();
            }
        })
    }
    if (window.addEventListener) window.addEventListener('load', ready, false);
    else if (window.attachEvent) window.attachEvent('onload', ready);
    /*  else  // use this 'else' statement for very old browsers :)
        window.onload=ready
    */
}
readyList = [];
function onReady(handler) {  
    if (!readyList.length) { 
        bindReady(function() { 
            for(var i=0; i<readyList.length; i++) { 
                readyList[i]() 
            } 
        }) 
    }   
    readyList.push(handler) 
}

window.onresize = WindowResized;
window.onscroll = WindowScrolled;

onReady(function() {
  setScanWrapXY(document.getElementById('Payair_scanwrap-' + Payair_DATA.articleID));
  setScanWrapXY(document.getElementById('Payair_scanwrap_clickable-' + Payair_DATA.articleID));
});
