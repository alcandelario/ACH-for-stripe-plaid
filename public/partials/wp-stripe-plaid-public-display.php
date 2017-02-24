<?php
/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       htps://www.justinwhall.com
 * @since      1.0.0
 *
 * @package    Wp_Stripe_Plaid
 * @subpackage Wp_Stripe_Plaid/public/partials
 */

global $post;

setlocale(LC_MONETARY, 'en_US');

// Helps determine what "name-based" fields to show
$is_org = false;

// Determine request type
$is_charged   = ( isset( $_GET[ 'charge' ] ) && isset( $_GET[ 'store_name' ] ) ) ? true : false; 
$error        = ( isset( $_GET[ 'charge_failed' ] ) ) ? true : false; 

// For checkouts.js, default to the test key if live key isn't enabled
if ( $this->settings[ 'sp_environment' ] !== 'live' || ! isset( $this->settings[ 'sp_environment' ] ) ) {
  $sc_pub_key = sanitize_text_field( $this->settings[ 'stripe_test_public_api_key' ] );
}
else {
  $sc_pub_key   = sanitize_text_field( $this->settings[ 'stripe_live_public_api_key' ] );  
}

// See if we can prefill email from query string
$cus_email   = sanitize_email( $_GET[ 'email' ] );

if( isset( $_GET[ 'orgname' ] ) || isset( $_GET[ 'store_name' ] ) ) {
  $is_org = true;
  $name  = ( isset( $_GET[ 'orgname' ] ) ) ? $_GET[ 'orgname' ]: $_GET[ 'store_name' ];
  
  $cus_name = sanitize_text_field( $name );
}

// See if we can prefill first/last name from query string
if( ! $is_org ) {
  $firstName = sanitize_text_field( $_GET[ 'firstName' ] );
  $lastName = sanitize_text_field( $_GET[ 'lastName' ] );
  
  $cus_name = trim( "$firstName $lastName" );
}

// See if we can prefill an invoice number from query string 
$cus_invoice = sanitize_text_field( $_GET[ 'invoice' ] );

// See if we can prefill an amount from query string
$amount = ( isset( $_GET[ 'amount' ] ) ) ? (int) $_GET[ 'amount' ] : null; 

// Build the query string stripe will send back to us
$redirect_params = add_query_arg( [ 'orgname' => rawurlencode( $cus_name ), 'email' => $cus_email, 'invoice' => $cus_invoice, 'amount' => $amount ] );

// Do we have something set for the live key?
$enable = ( ! empty( $sc_pub_key ) ) ? true : false;
?>

<?php if( $enable ) : ?>  
  <div class='row'>
    <div class='col-md-6 col-md-offset-3' style='height:auto; padding-top: 15px;'>
       <h3 class='text-center'>Checkout</h3>
      
      <?php if( ! $is_charged && ! $error ) : ?>

        <form id='sc-form' action="javascript:void(0);" novalidate>
          <div class="clearfix"> 
              <div class="col-lg-12">    
                
                <div class="form-group">
                    <h4><span class="req">*</span> Required Fields</h4>

                    <?php if( ! $is_org ) : ?>
                      <label for="BILLTOFIRSTNAME"> <span class="req">*</span> Card Holder First Name</label>
                      <input id='firstName' type="text" name="BILLTOFIRSTNAME" value="<?php echo esc_attr( $firstName ); ?>" class="form-control" placeholder="First Name" required="" style="">
                      
                      <label for="BILLTOLASTNAME"> <span class="req">*</span>Card Holder Last Name</label>
                      <input id='lastName' type="text" name="BILLTOLASTNAME" value="<?php echo esc_attr( $lastName ); ?>" class="form-control" placeholder="Last Name" required="">
                    <?php else : ?>
                      <label for="BILLTOFIRSTNAME"> <span class="req">*</span> Organization Name</label>
                      <input id='orgName' type="text" name="BILLTOORGNAME" value="<?php echo esc_attr( $cus_name ); ?>" class="form-control" placeholder="Organization Name" required="" style="">
                    <?php endif; ?>

                    <label for="BILLTOEMAIL"><span class="req">*</span>Card Holder Email</label>
                    <input id='email' type="email" name="BILLTOEMAIL" value="<?php echo esc_attr( $cus_email ); ?>" class="form-control" placeholder="Email" required="">

                    <label for="invoice_number"> <span class="req">*</span>Order/Invoice Number</label>
                    <input id='invoice' maxlength="20" name="INVNUM" required="" class="form-control" size="12" value="<?php echo esc_attr( $cus_invoice ); ?>">
                </div>
                <!-- /.form-group -->

                <div class="form-group">
                    <label for="subTotal"><span class="req">*</span>Payment Amount</label> <small>- Note: we assess a credit card fee of 3% for Payments</small>
                    <input id="subTotal" name="subTotal" required="" class="form-control" type="text" pattern="^\$?\d+(,\d{3})*(\.[0-9]{2})?$" value="<?php echo esc_attr( money_format( '%.2n', $amount / 100 ) ); ?>" placeholder=" Enter Digits Only: Example: 92.50">
                        
                    <label>Total</label>
                    <input id="sp-amount" type="text" name="AMT" value="" class="form-control" placeholder="0.00" readonly="">

                    <div class='row'>
                        <div class='col-md-6'>
                            <button data-publickey="<?php echo $sc_pub_key; ?>" style='margin-bottom:15px;' id='checkoutButton' class="btn btn-large btn-block btn-primary">Pay with Credit Card</button>
                        </div>
                        <div class='col-md-6'>
                            <button data-publickey="<?php echo $this->settings['plaid_public_key']; ?>" style='margin-bottom:15px;' id='linkACHButton' class="btn btn-large btn-block btn-primary">Pay with Bank Account</button>

                            <div class="sp-spinner">
                              <div class="double-bounce1"></div>
                              <div class="double-bounce2"></div>
                            </div>
                        </div>

                        <div class='col-md-12'><button id='payACHButton' class="btn btn-block btn-primary">Finish paying with bank account</button></div>

                    </div>
                </div>
                <!--/.form-group -->
              </div>
              <!--/.col-md-4 -->
          </div>
        </form>

        <div class='text-center' id="sp-response"></div>

      <?php endif; ?>

      <div style='font-size:24px;'>
        <?php if( $error ) : ?>
            <i style='font-size:24px; color:red;' class='fa fa-exclamation-circle'></i>&nbsp;There was a problem processing your payment. Please try again or contact <a href='mailto:<?php echo esc_attr( DSP_EMAIL ); ?>'><?php echo esc_html( DSP_EMAIL ); ?></a> for details.
        
        <?php elseif( $is_charged ): ?>
            <i class='fa fa-check-circle-o' style='font-size: 24px; color: green;'></i>&nbsp;Payment received, thank you!
        <?php endif; ?>
      </div>

    </div>
  </div>

<?php else : ?>
  
  <div class='row'><div class='col-md-4 col-md-offset-4'><h3>Sorry, the checkout service is temporarily disabled (no live API publish key found).</h3></div></div>

<?php endif; ?>