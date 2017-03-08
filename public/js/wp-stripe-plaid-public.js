(function( $ ) {
	'use strict';

	var opt = {};
	
	// Plaid Link Init
	var linkHandler = Plaid.create({
			env							: 'tartan',
			clientName			: getCustomerName(),
			key	 						: $('#linkACHButton').data( 'publickey' ),
			product 				: 'auth',
			selectAccount 	: true,
			onSuccess 			: function(public_token, metadata) {
				$('#checkoutButton').hide();
				$('#linkACHButton').hide();
				$('#payACHButton').show();
				opt.public_token = public_token;
				opt.account_id = metadata.account_id;
			},
	});

	// Stripe Checkout Init
	var checkoutHandler = StripeCheckout.configure({
		key								: $('#checkoutButton').data( 'publickey' ),
		image 						: 'https://stripe.com/img/documentation/checkout/marketplace.png',
		locale 						: 'auto',
		allowRememberMe 	: false,
		token 						: function(token) {
			
			// You can access the token ID with `token.id`.
			// Get the token ID to your server-side code for use.
			opt.public_token = token.id;

			// Process the charge server-side
			callStripe();
		}
	});

	// Calculate total with processing fees automatically
	document.getElementById( 'subTotal' ).addEventListener("keyup", calculatePercent);

	// Triggers the Plaid Link UI
	document.getElementById( 'linkACHButton' ).addEventListener('click', clickPlaidLink );

	// Process the Plaid token server-side
	document.getElementById( 'payACHButton' ).addEventListener( 'click', callPlaid );

	// Triggers the Sripe Checkout UI
	document.getElementById( 'checkoutButton' ).addEventListener('click', clickStripeCheckout );

	// Close Stripe Checkout on page navigation:
	window.addEventListener('popstate', function() {
		checkoutHandler.close();
	});

	// Call this upon page load since query string may pre-fill the total
	calculatePercent();

	function configFormValidation() {

		// Define a custom validation
		$.validator.addMethod("notJustASpace", function(value, element) { 
			return ( $.trim( value ).length > 0 ) ? true : false; 
		}, "Required");

		// Override jquery validate plugin defaults
		$.validator.setDefaults({
				highlight: function(element) {
						$(element).closest('.form-group').addClass('has-error');
				},
				unhighlight: function(element) {
						$(element).closest('.form-group').removeClass('has-error');
				},
				errorElement: 'span',
				errorClass: 'help-block',
				errorPlacement: function(error, element) {
						if(element.parent('.input-group').length) {
								error.insertAfter(element.parent());
						} else {
								error.insertAfter(element);
						}
				}
		});

		// Config the validate options
		var valid = $( '#sc-form' ).validate( { 
			rules     : { 
				BILLTOFIRSTNAME : {
					minlength   	: 1,
					required    	: true,
					notJustASpace	: true,
				},
				BILLTOLASTNAME : {
					minlength   	: 1,
					required    	: true,
					notJustASpace	: true,
				}, 
				BILLTONAME : {
					minlength   	: 1,
					required    	: true,
					notJustASpace	: true,
				}, 
				INVOICENUMBER : {
					minlength   	: 1,
					required    	: true,
					notJustASpace	: true,
				},
				BILLTOEMAIL   : { 
					required    : {
						depends:function(){
								$(this).val($.trim($(this).val()));
								return true;
						}
					},
					email       : true
				}, 
				SUBTOTAL   : {
					required    : true,
					pattern     : /^\$?\d+(,\d{3})*(\.[0-9]{2})?$/
				}, 
			},
		});
	}

	function validateForm() {

		// Setup the rules for form validation
		configFormValidation();

		// Send the request if we have required info
		return $( '#sc-form' ).valid();
	}

	/**
	 * Handler for Plaid Link
	 * 
	 * @param  {object} e - DOM event 
	 */
	function clickPlaidLink( e ) {

		if( validateForm() ) {
			linkHandler.open();
		}
		
		e.preventDefault();
	}

	/**
	 * Handler for Stripe Checkout
	 * 
	 * @param  {object} e - DOM event 
	 */
	function clickStripeCheckout( e ) {
		var customerName = getCustomerName();
		var amount = getAmount();
		var invoice = 'Invoice: ' + $( '#invoice' ).val();
		var email = $( '#email' ).val();

		if( validateForm() ) {

			// Open Checkout with further options:
			checkoutHandler.open({
				name 					: customerName,
				email 				: email,
				description 	: invoice,
				zipCode 			: true,
				amount 				: parseInt( amount.amount ),
			});
		}

		e.preventDefault();
	}

	function getCustomerName() {
		var customerName = '';

		if( $( '#orgName' ).length > 0 ) {
			customerName = $( '#orgName' ).val();
		}
		else if( $( '#firstName' ).length > 0 ) {
			customerName = $( '#firstName' ).val() + ' ' + $( '#lastName' ).val();
		}
		else if( $( '#name' ).length > 0 ) {
			customerName = $( '#name' ).val();
		}

		return customerName;
	}

	function getAmount() {
		
		var isACH = ( typeof opt.isACH === 'undefined' || opt.isACH === null ) ? false : true;
		var input = ( ! isACH ) ? $( '#sp-amount' ) : $( '#subTotal' );

		// Format amount
		var amountInt = input.val() * 1;
		var amountFloat = amountInt.toFixed( 2 );
		var amount = String( amountFloat.replace( '.', '' ) );

		var subTotal = $( '#subTotal' ).val();

		return { 
			isACH 		: isACH,
			asInt 		: amountInt,
			amount 		: amount,
			asFloat 	: amountFloat, 
			subTotal 	: subTotal,
			fees 			: ( amountFloat - subTotal ).toFixed( 2 ),
		};
	}

	/**
	 * AJAX call to process Plaid token
	 */
	function callPlaid() {

		var buttonId = '#payACHButton';
		var action = 'call_plaid';
		var payload = { account_id : opt.account_id };

		if( validateForm() ) {
			opt.isACH = true;
			ajax( action, payload, buttonId );
		}
	}

	/**
	 * AJAX call to process Stripe Checkout token
	 */
	function callStripe() {

		var buttonId = '#checkoutButton';
		var action = 'call_stripe';
		var payload = {};

		ajax( action, payload, buttonId );
	}

	function ajax( action, extraPayload, buttonId ) {

		// Format amount
		var amount 			= getAmount();
		var amountInt 	= amount.asInt;
		var amountFloat = amount.asFloat;
		var subTotal 		= amount.subTotal;
		var fees 				= amount.fees;
		amount 					= amount.amount;

		if ( amountInt >= .50 ) {
			$('.sp-spinner').css('opacity', 1);
			$( buttonId ).off('click');

			var payload = {
				nonce        	: ajax_object.ajax_nonce,
				email  				: $('#email').val(),	
				action       	: action,
				amount       	: amount,
				description  	: $('#invoice').val(),
				public_token 	: opt.public_token,
				customer_name : getCustomerName(),
				sub_total  		: subTotal,
				fees 					: fees,
			};

			// Merge in any additional payload data
			$.extend( payload, extraPayload );

			$.ajax({
				url     : ajax_object.ajax_url,
				type    : 'POST',
				data    : payload,
				success : function( data ){
					ajaxCallback( data );
				}
			});

		} else {
			addError( '<h4>Amount must be at least 50 cents</h4>' );
		}
	}

	function getReceiptData() {
		
		var amountObj = getAmount();
		
		var data = {
			header 		: '<h3>Success. Thank you for your payment.</h3>',
			name 			: getCustomerName(),
			email 		: $('#email').val(),
			invoice 	: $('#invoice').val(),
			amount  	: amountObj.asFloat,
			subTotal 	: amountObj.subTotal,
			date 			: new Date().toLocaleString(),
		}

		data.fee = ( data.amount - data.subTotal ).toFixed( 2 );
				
		return data;
	}

	function ajaxCallback( data ) {
		$('.sp-spinner').css('opacity', 0);

		if ( data.error ) {
			addError( '<h4>There was an error processing you payment.</h4>' );
		} else {
			var data = getReceiptData();

			$('#sc-form').slideUp('slow');
			$('#sp-response' ).removeClass( 'hidden' ).show();
			$('#sp-response').find( '.inner' ).html( data.header );
			$('#sp-response').find( '#rcpt-name' ).text( data.name );
			$('#sp-response').find( '#rcpt-email' ).text( data.email );
			$('#sp-response').find( '#rcpt-date' ).text( data.date );
			$('#sp-response').find( '#rcpt-invoice' ).text( data.invoice );
			$('#sp-response').find( '#rcpt-subtotal' ).text( '$' + data.subTotal );
			$('#sp-response').find( '#rcpt-fee' ).text( '$' + data.fee );
			$('#sp-response').find( '#rcpt-total' ).text( '$' + data.amount );
			$('#sp-response').removeClass('error');
			$('#sp-response').addClass('success');
		}
	}

	/**
	 * Add processing fee to subtotal
	 */
	function calculatePercent() {       
		var transaction = document.getElementById( "subTotal" ),
				total = document.getElementById( "sp-amount" ),
				transValue = parseFloat( transaction.value.replace(/,/g, '')) || 0,
				percent = 3 / 100;

		total.value = Number(transValue + transValue * percent).toFixed(2);
	}

	function addError( message ){
		$('#sp-pay').on('click', callPlaid );
		$('#sp-response').removeClass( 'hidden' ).show();
		$('#sp-response').html( '<h4>' + message + '</h4>' );
		$('#sp-response').addClass('error');
		$('#sp-response').removeClass('success');
	}

})( jQuery );
