<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       htps://www.justinwhall.com
 * @since      1.0.0
 *
 * @package    Wp_Stripe_Plaid
 * @subpackage Wp_Stripe_Plaid/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wp_Stripe_Plaid
 * @subpackage Wp_Stripe_Plaid/public
 * @author     Justin W Hall <justin@windsorup.com>
 */
class Wp_Stripe_Plaid_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The plugin settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $settings    The current settings of this plugin.
	 */
	private $settings;

	/**
	 * The plugin settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $user_message  holds message(s) for user if they don't have Stripe or Plaid creds.
	 */
	private $user_message = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->settings = get_option( 'stripe_plaid_settings' );
		$this->has_creds();
		add_shortcode( 'wp_stripe_plaid', array( $this, 'render_form' ) );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-stripe-plaid-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-stripe-plaid-public.js', array( 'jquery', 'jquery-validate', 'jquery-validate-addtl', 'stripe_plaid', 'stripe_checkout' ), $this->version, true );
		wp_enqueue_script( 'jquery-validate', plugin_dir_url( __FILE__ ) . 'js/vendor/jquery-validate/jquery.validate.min.js', array( 'jquery' ), null, true );
		wp_enqueue_script( 'jquery-validate-addtl', plugin_dir_url( __FILE__ ) . 'js/vendor/jquery-validate/additional-methods.min.js', array( 'jquery-validate' ), null, true );
		wp_enqueue_script( 'stripe_plaid', 'https://cdn.plaid.com/link/v2/stable/link-initialize.js', array(), null, true );
		wp_enqueue_script( 'stripe_checkout', 'https://checkout.stripe.com/checkout.js', array(), null, true );
		wp_localize_script($this->plugin_name, 'ajax_object', array( 'ajax_url' => admin_url('admin-ajax.php'), 'ajax_nonce' => wp_create_nonce('stripe_plaid_nonce') ) );
	}

	public function has_creds(){
		
		// Check for test creds if in test mode, otherwise live key
		if ( $this->settings['sp_environment'] === "test" ) {
		
			if ( !strlen( trim( $this->settings['stripe_test_api_key'] ) ) ) {
				$this->user_message[] = 'Missing Stripe test API Key';
			}

		} else {

			if ( !strlen( trim( $this->settings['stripe_live_api_key'] ) ) ) {
				$this->user_message[] = 'Missing Stripe live API Key';
			}

		}

		// Plaid keys
		if ( !strlen( trim( $this->settings['plaid_client_id'] ) ) ) {
			$this->user_message[] = 'Missing Plaid client ID';
		}

		if ( !strlen( trim( $this->settings['plaid_secret'] ) ) ) {
			$this->user_message[] = 'Missing Plaid Secret';
		}

		if ( !strlen( trim( $this->settings['plaid_public_key'] ) ) ) {
			$this->user_message[] = 'Missing Plaid public key';
		}
	}

	public function render_form(){

		// Show the frontend form
		if ( empty( $this->user_message ) ) {
			require_once( WP_STRIPE_PLAID_PATH . '/public/partials/wp-stripe-plaid-public-display.php' );
		} 

		// Errors, notices, etc
		else {

			foreach ( $this->user_message as $message ) {
				echo '- ' . $message . '<br />';
			}
		}
	}

	public function setStripeAPIKey() {

		// Live or test?
		$stripe_key = ( $this->settings['sp_environment'] === 'live' ) ? $this->settings['stripe_live_api_key'] : $this->settings['stripe_test_api_key'];
		
		\Stripe\Stripe::setApiKey( $stripe_key );
	}

	public function stripe_charge( $amount, $currency, $source, $description, $customer = false, $meta = false ){

		$this->setStripeAPIKey();

		$return = array( 'error' => false );

		try {

			$params = array(
				"amount"      => $amount,
				"currency"    => $currency,
				"description" => $description
			);

			if( $customer ) {
				$params[ 'customer' ] = $customer;
			}
			else if( $source ) {
				$params[ 'source' ] = $source;
			}

			if ( $meta ) {
				$params[ 'metadata' ] = $meta;
			}

			$charge = \Stripe\Charge::create( $params );

		} catch(\Stripe\Error\Card $e) {
			
			// Since it's a decline, \Stripe\Error\Card will be caught
			$return = $e->getJsonBody();

		} catch (\Stripe\Error\RateLimit $e) {
			// Too many requests made to the API too quickly
			$return = $e->getJsonBody();

		} catch (\Stripe\Error\InvalidRequest $e) {

			// Invalid parameters were supplied to Stripe's API
			$return = $e->getJsonBody();

		} catch (\Stripe\Error\Authentication $e) {

			// Authentication with Stripe's API failed
			$return = $e->getJsonBody();

		} catch (\Stripe\Error\ApiConnection $e) {

			// Network communication with Stripe failed
			$return = $e->getJsonBody();

		} catch (\Stripe\Error\Base $e) {

			// Display a very generic error to the user, and maybe send
			$return = $e->getJsonBody();

		} catch (Exception $e) {

			// Something else happened, completely unrelated to Stripe
			$return = $e->getJsonBody();

		}

		// log error if there is any. 
		if ( $return['error'] && $this->settings['log'] === 'on' ) {
			$message = 'DESCRIPTION: ' . $description . ' CHARGE: ' . $amount . ' TYPE: ' . $return['error']['type'] . ' PARAM: ' . $return['error']['param'] . ' MESSAGE: ' . $return['error']['message'];
			Wp_Stripe_Plaid_Public::write_error( $message );
		}

		return $return;
	}

	public function stripe_create_customer( $token, $email, $metadata, $hasSource = false ) {
		$customer = false;

		$customerData = ( ! $hasSource ) ? [ 'email' => $email, 'card' => $token ] : [ 'email' => $email, 'source' => $token ];

		if( isset( $token ) && isset( $email ) ) {

			try {

				$this->setStripeAPIKey();

				$customerData[ 'description' ] = 'Customer added via stripe-plaid-ach-cc WP plugin';

				if( $metadata ) {
					$customerData[ 'metadata' ] = $metadata;
				}

				$customer = \Stripe\Customer::create( $customerData );
			}
			catch (Exception $e ) {
				
			}
		}

		return $customer;
	}

	/**
	 * Create a charge via token generated by Stripe's checkout.js
	 *
	 * @since    1.0.1
	 */
	public function call_stripe(){

		check_ajax_referer( 'stripe_plaid_nonce', 'nonce' );

		$email 					= $_POST[ 'email' ];		
		$token         	= $_POST[ 'public_token' ];
		$amount        	= $_POST[ 'amount' ];
		$description   	= $_POST[ 'description' ];
		$name      			= $_POST[ 'customer_name' ];
		
		$new_customer = $this->stripe_create_customer( $token, $email, [ 'name' => $name ], true );

		$cus_id = ( isset( $new_customer ) && isset( $new_customer[ 'id' ] ) ) ? $new_customer[ 'id' ] : false;

		// Create the meta data to attach to the Stripe transaction
		$meta = [
			'email' 	=> $email,
			'name'		=> $name,
			'invoice' => $description,
		];
	

		// Create the Credit/Debit charge
		$charge = $this->stripe_charge( $amount, 'USD', $token, $description, $cus_id, $meta );	

		wp_send_json( $charge );

		wp_die();
	}

	public function call_plaid(){

		check_ajax_referer( 'stripe_plaid_nonce', 'nonce' );

		$email 				= $_POST[ 'email' ];		
		$token       	= $_POST[ 'public_token' ];
		$amount      	= $_POST[ 'amount' ];
		$description 	= $_POST[ 'description' ];
		$name      		= $_POST[ 'customer_name' ];	
		$account 			= $_POST[ 'account_id' ];

		// Create the payload for Plaid
		$data = array(
					'client_id'    	=> $this->settings[ 'plaid_client_id' ],
					'secret'       	=> $this->settings[ 'plaid_secret' ],
					'public_token'	=> $token,
					'account_id'   	=> $account,
		);

		$string = http_build_query( $data );

		// Initialize session
		$ch = curl_init( "https://tartan.plaid.com/exchange_token" );

		// Set options
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $string );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		// Execute session
		$keys = curl_exec( $ch );
		$keys = json_decode( $keys );

		// Stripe token
		$token = $keys->stripe_bank_account_token;
		
		// Close session
		curl_close( $ch );

		// Create the meta data to attach to the Stripe transaction
		$meta = [
			'email' 	=> $email,
			'name'		=> $name,
			'invoice' => $description,
		];

		$new_customer = $this->stripe_create_customer( $token, $email, [ 'name' => $name ], true );

		$cus_id = ( isset( $new_customer ) && isset( $new_customer[ 'id' ] ) ) ? $new_customer[ 'id' ] : false;

		// Create the ACH charge
		$charge = $this->stripe_charge( $amount, 'USD', $token, $description, $cus_id, $meta );

		wp_send_json( $charge );

		wp_die();
	}

	static function write_error( $message ) {
		
		$ts = date( '[ m.d.Y | H:i:s e ]' );
		$message = $ts . " - " . $message . "\n" ;
		error_log( $message, 3, WP_STRIPE_PLAID_PATH . 'errorlog.log');
	}

}
