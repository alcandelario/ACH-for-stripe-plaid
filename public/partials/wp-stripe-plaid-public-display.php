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

setlocale(LC_MONETARY, 'en_US');

// Helps determine what "name-based" fields to show
$is_org = false;

// For checkouts.js, default to the test key if live key isn't enabled
if ( $this->settings[ 'sp_environment' ] !== 'live' || ! isset( $this->settings[ 'sp_environment' ] ) ) {
  $sc_pub_key = sanitize_text_field( $this->settings[ 'stripe_test_public_api_key' ] );
}
else {
  $sc_pub_key   = sanitize_text_field( $this->settings[ 'stripe_live_public_api_key' ] );  
}

// See if we can prefill email from query string
$cus_email   = sanitize_email( $_GET[ 'email' ] );

if( isset( $_GET[ 'billtoname' ] ) || isset( $_GET[ 'store_name' ] ) ) {
  $is_org = true;
  $name  = ( isset( $_GET[ 'billtoname' ] ) ) ? $_GET[ 'billtoname' ]: $_GET[ 'store_name' ];
  
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

// Do we have something set for the live key?
$enable = ( ! empty( $sc_pub_key ) ) ? true : false;
?>

<?php if( $enable ) : ?>  
  <div class='row'>
    <div class='col-md-6 col-md-offset-3' style='height:auto; padding-top: 15px;'>
       <h3 class='text-center'>Checkout</h3>

        <form id='sc-form' action="javascript:void(0);" novalidate>
          <div class="clearfix"> 
              <div class="col-lg-12">                 
                <h4><span class="req">*</span> Required Fields</h4>

                <div class='clearfix'>
                  <?php if( false ) : ?>
              
                    <!-- <div class="form-group">
                      <label for="BILLTOFIRSTNAME"> <span class="req">*</span> Card Holder First Name</label>
                      <input id='firstName' type="text" name="BILLTOFIRSTNAME" value="<?php //echo esc_attr( $firstName ); ?>" class="form-control" placeholder="First Name" required="" style="">
                    </div>
                    
                    <div class="form-group">
                      <label for="BILLTOLASTNAME"> <span class="req">*</span>Card Holder Last Name</label>
                      <input id='lastName' type="text" name="BILLTOLASTNAME" value="<?php //echo esc_attr( $lastName ); ?>" class="form-control" placeholder="Last Name" required="">
                    </div> -->
              
                  <?php else : ?>
                    <div class="form-group">
                      <label for="BILLTONAME"> <span class="req">*</span>Name</label>
                      <input id='name' type="text" name="BILLTONAME" value="<?php echo esc_attr( $cus_name ); ?>" class="form-control" placeholder="Name" required="" style="">
                    </div>
                  <?php endif; ?>

                  <div class="form-group">
                    <label for="BILLTOEMAIL"><span class="req">*</span>Email</label>
                    <input id='email' type="email" name="BILLTOEMAIL" value="<?php echo esc_attr( $cus_email ); ?>" class="form-control" placeholder="Email" required="">
                  </div>
                  <div class="form-group">
                    <label for="INVOICENUMBER"> <span class="req">*</span>Order/Invoice Number</label>
                    <input id='invoice' maxlength="20" name="INVOICENUMBER" required="" class="form-control" size="12" value="<?php echo esc_attr( $cus_invoice ); ?>">
                  </div>

                  <div class="form-group">
                    <label for="SUBTOTAL"><span class="req">*</span>Payment Amount</label> <small>- Note: we assess a credit card fee of 3% for Payments</small>
                    <input id="subTotal" name="SUBTOTAL" required="" class="form-control" type="text" pattern="^\$?\d+(,\d{3})*(\.[0-9]{2})?$" value="<?php echo esc_attr( money_format( '%.2n', $amount / 100 ) ); ?>" placeholder=" Enter Digits Only: Example: 92.50">
                  </div>

                  <div class="form-group">
                    <input id="sp-amount" type="hidden" name="AMT" value="" class="form-control" placeholder="0.00" readonly="">
                  </div>
                </div>

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

                    <div class='col-md-12'><button id='payACHButton' class="btn btn-block btn-primary">Complete payment with bank account</button></div>
                </div>
              </div>
              <!--/.col-lg-12 -->
          </div>
        </form>

        <div class='text-center hidden' id="sp-response">
          <div class='inner'></div>

          <div id='receipt' class="row">
            <div class="col-lg-12">
                <div class="row">
                    <div class="col-xs-6 text-left">
                        <address>
                            <strong>Name: <span id='rcpt-name'></span></strong>
                            <br/>
                            <span>Email: <span id='rcpt-email'></span></span>
                            <br/>
                        </address>
                    </div>
                    <div class="col-xs-6 text-right">
                        <div><em>Date: <span id='rcpt-date'></span></em></div>
                        <div><em>Invoice #: <span id='rcpt-invoice'></span></em></div>
                    </div>
                </div>
                <div class="row">
                    <div class="text-center">
                        <h3>Receipt</h3>
                    </div>
                    </span>
                    <table class="table">
                        <tbody>
                            <tr>
                                <td></td>
                                <td></td>
                                <td class="text-right">
                                  <p>
                                      <strong>Subtotal: </strong>
                                  </p>
                                  <p>
                                      <strong>Processing Fee: </strong>
                                  </p>
                                </td>
                                <td class="text-center">
                                  <p>
                                      <strong id='rcpt-subtotal'></strong>
                                  </p>
                                  <p>
                                      <strong id='rcpt-fee'></strong>
                                  </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <td></td>
                                <td></td>
                                <td class="text-right"><h4><strong>Total: </strong></h4></td>
                                <td class="text-center text-success"><h4><strong id='rcpt-total'></strong></h4></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
          </div>
        </div><!-- #sp-response -->
    </div><!-- /.col-md-6 col-md-offset-3 -->
  </div><!-- /.row -->

<?php else : ?>
  
  <div class='row'><div class='col-md-4 col-md-offset-4'><h3>Sorry, the checkout service is temporarily disabled (no live API publish key found).</h3></div></div>

<?php endif; ?>