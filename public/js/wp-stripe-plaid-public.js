(function( $ ) {
	'use strict';

	var opt = {};
	
	// Plaid Link
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

	var checkoutHandler = StripeCheckout.configure({
		key								: $('#checkoutButton').data( 'publickey' ),
		image 						: 'https://stripe.com/img/documentation/checkout/marketplace.png',
		locale 						: 'auto',
		allowRememberMe 	: false,
		token 						: function(token) {
			
			// You can access the token ID with `token.id`.
			// Get the token ID to your server-side code for use.
			opt.public_token = token.id;

			// Create the customer and process the charge server-side
			callStripe();
		}
	});

	// Calculate total with processing fees automatically
	document.getElementById( 'subTotal' ).addEventListener("keyup", calculatePercent);

	// Call this since the subtotal might be prefilled upon page load
	calculatePercent();

	// Trigger the Link UI
	document.getElementById( 'linkACHButton' ).addEventListener('click', function(e) {
		
		linkHandler.open();
		e.preventDefault();
	});

	// Trigger the Checkout UI
	document.getElementById( 'checkoutButton' ).addEventListener('click', function(e) {
		
		var customerName = getCustomerName();
		var amount = getAmount();
		var invoice = 'Invoice: ' + $( '#invoice' ).val();
		var email = $( '#email' ).val();

		// Open Checkout with further options:
		checkoutHandler.open({
			name 					: customerName,
			email 				: email,
			description 	: invoice,
			zipCode 			: true,
			amount 				: amount.amount,
		});

		e.preventDefault();
	});

	// Get token from plaid
	document.getElementById( 'payACHButton' ).addEventListener( 'click', callPlaid );

	// Close Checkout on page navigation:
	window.addEventListener('popstate', function() {
		checkoutHandler.close();
	});

	function getCustomerName() {
		var customerName = '';

		if( $( '#orgName' ).length > 0 ) {
			customerName = $( '#orgName' ).val();
		}
		else if( $( '#firstName' ).length > 0 ) {
			customerName = $( '#firstName' ).val() + ' ' + $( '#lastName' ).val();
		}

		return customerName;
	}

	function getAmount() {
		// format amount
		var amountInt = $( '#sp-amount' ).val() * 1;
		var amountFloat = amountInt.toFixed( 2 );
		var amount = String( amountFloat.replace( '.', '' ) );

		return { asInt : amountInt, asFloat : amountFloat, amount : amount };
	}

	function callPlaid() {

		// Format amount
		var amount = getAmount();
		var amountInt = amount.asInt;
		var amountFloat = amount.asFloat;
		amount = amount.amount;

		$('#sp-response').hide();

		if ( amountInt >= .50 ) {
			$('.sp-spinner').css('opacity', 1);
			$('#payACHButton').off('click');

			var data = {
				nonce        	: ajax_object.ajax_nonce,
				email  				: $('#email').val(),	
				action       	: 'call_plaid',
				amount       	: amount,
				account_id   	: opt.account_id,
				description  	: $('#invoice').val(),
				public_token 	: opt.public_token,
				customer_name : getCustomerName(),
			};

			$.ajax({
				url     : ajax_object.ajax_url,
				type    : 'POST',
				data    : data,
				success : function( data ){
					ajaxCallback( data );
				}
			});

		} else {
			addError( 'Amount must be at least 50 cents' );
		}
	}

	function callStripe() {

		// Format amount
		var amount = getAmount();
		var amountInt = amount.asInt;
		var amountFloat = amount.asFloat;
		amount = amount.amount;

		$('#sp-response').hide();

		if ( amountInt >= .50 ) {
			$('.sp-spinner').css('opacity', 1);
			$('#checkoutButton').off('click');

			var data = {
				nonce        	: ajax_object.ajax_nonce,
				email  				: $('#email').val(),	
				action       	: 'call_stripe',
				amount       	: amount,
				description  	: $('#invoice').val(),
				public_token 	: opt.public_token,
				customer_name : getCustomerName(),
			};

			$.ajax({
				url     : ajax_object.ajax_url,
				type    : 'POST',
				data    : data,
				success : function( data ){
					ajaxCallback( data );
				}
			});

		} else {
			addError( 'Amount must be at least 50 cents' );
		}
	}

	function addError( message ){
		$('#sp-pay').on('click', callPlaid );
		$('#sp-response').show();
		$('#sp-response').text( message );
		$('#sp-response').addClass('error');
		$('#sp-response').removeClass('success');
	}

	function ajaxCallback( data ) {
		$('.sp-spinner').css('opacity', 0);

		if ( data.error ) {
			addError( 'There was an error processing you payment.' );
		} else {
			$('#sc-form').slideUp('slow'); //fadeTo('fast', 0).hide();
			$('#sp-response').show();
			$('#sp-response').text('Success. Thank you for your payment.');
			$('#sp-response').removeClass('error');
			$('#sp-response').addClass('success');
		}
	}

	function calculatePercent() {       
		var transaction = document.getElementById( "subTotal" ),
				total = document.getElementById( "sp-amount" ),
				transValue = parseFloat( transaction.value.replace(/,/g, '')) || 0,
				percent = 3 / 100;

		total.value = Number(transValue + transValue * percent).toFixed(2);
	}

})( jQuery );
