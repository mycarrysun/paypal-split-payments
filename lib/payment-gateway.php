<?php
/**
 * Payment Gateway for Multiple PayPal payment gateway accounts
 *
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'plugins_loaded', 'nw_init_split_payments_gateway' );
if ( ! function_exists( 'nw_init_split_payments_gateway' ) ) {
	function nw_init_split_payments_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		class WC_Gateway_NW_Split_Payments_Gateway extends WC_Payment_Gateway {
			public function __construct() {
				// Init Properties
				$this->id = NW_PAYMENT_GATEWAY_ID;

				$this->icon               = '';
				$this->has_fields         = true;
				$this->method_title       = 'PayPal Split Payments';
				$this->method_description = 'This is the payment gateway for PayPal Pro Split Payments. It takes the products'
				                            . ' from a purchase and splits the payments between 2 merchant accounts. Currently set up for PayPal Payflow Pro only.';
				$this->supports           = [ 'default_credit_card_form', 'products' ];

				//Get WP Options
				$settings = get_option( 'woocommerce_' . NW_PAYMENT_GATEWAY_ID . '_settings' );

				// Set Gateway Title
				$this->title = isset( $settings['title'] ) && ! empty( $settings['title'] ) ? $settings['title'] : 'PayPal Split Payments';

				//Set Merchant 1 Name
				$this->merchant_name = isset( $settings['merchant_name'] ) && ! empty( $settings['merchant_name'] )
					? $settings['merchant_name']
					: 'Merchant 1';

				// Set Merchant 2 Name
				$this->merchant_name2 = isset( $settings['merchant_name2'] ) && ! empty( $settings['merchant_name2'] )
					? $settings['merchant_name2']
					: 'Merchant 2';

				$this->init_form_fields();
				$this->init_settings();

				// Turn these settings into variables we can use
				foreach ( $this->settings as $setting_key => $value ) {
					$this->$setting_key = $value;
				}

				add_action( 'woocommerce_update_options_payment_gateways_' . NW_PAYMENT_GATEWAY_ID,
					[ $this, 'process_admin_options' ] );
			}

			public function init_form_fields() {
				$this->form_fields = [
					'enabled'              => [
						'title'   => __( 'Enable / Disable', 'woocommerce' ),
						'label'   => __( 'Enable this payment gateway.', 'woocommerce' ),
						'type'    => 'checkbox',
						'default' => 'no',
					],
					'title'                => [
						'title'       => __( 'Title', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This controls the credit card form label which the user sees during checkout.', 'woocommerce' ),
						'default'     => __( 'PayPal Split Payments', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_env'       => [
						'title'       => __( 'Sandbox Mode', 'woocommerce' ),
						'label'       => __( 'Enable sandbox mode?', 'woocommerce' ),
						'type'        => 'checkbox',
						'description' => __( 'Used for testing purposes. If you want live transactions, leave unchecked.', 'woocommerce' ),
						'default'     => __( 'no', 'woocommerce' ),
						'desc_tip'    => false,
					],
					'support_email'        => [
						'title'       => __( 'Support Email', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Enter the email you want to use for general support. This will be shown to the user in case there are problems with the order.', 'woocommerce' ),
						'default'     => __( 'support@rickhopcraftfoundation.org', 'woocommerce' ),
						'desc_tip'    => true,
					],

					// Merchant 1 Options
					'merchant_name'        => [
						'title'       => __( 'Merchant 1 Name', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Enter the name you want to use for the first merchant.', 'woocommerce' ),
						'default'     => __( 'Merchant 1', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'contact_email1'       => [
						'title'       => __( 'Contact Email', 'woocommerce' ) . '<br>(' . $this->merchant_name . ')',
						'type'        => 'text',
						'description' => __( 'Enter the email you want to use for the first merchant. This will be shown to the user in case there are problems with the transaction.', 'woocommerce' ),
						'default'     => __( 'email@sample.com', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_user'      => [
						'title'       => __( 'PayPal API User ', 'woocommerce' ) . '<br>(' . $this->merchant_name . ')',
						'type'        => 'text',
						'description' => __( 'Enter the username for the first Payflow Gateway merchant account.', 'woocommerce' ),
						'default'     => __( 'user123', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_vendor'    => [
						'title'       => __( 'PayPal API Vendor ', 'woocommerce' ) . '<br>(' . $this->merchant_name . ')',
						'type'        => 'text',
						'description' => __( 'Enter the vendor name for the first Payflow Gateway merchant account. If unsure, use the merchant username.', 'woocommerce' ),
						'default'     => __( 'user123', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_partner'   => [
						'title'       => __( 'PayPal API Partner ', 'woocommerce' ) . '<br>(' . $this->merchant_name . ')',
						'type'        => 'text',
						'description' => __( 'Enter the partner name for the first Payflow Gateway merchant account. If unsure, use "PayPal".', 'woocommerce' ),
						'default'     => __( 'PayPal', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_password'  => [
						'title'       => __( 'PayPal API Password ', 'woocommerce' ) . '<br>(' . $this->merchant_name . ')',
						'type'        => 'password',
						'description' => __( 'Enter the password for the first Payflow Gateway merchant account.', 'woocommerce' ),
						'desc_tip'    => true,
					],

					// Merchant 2 Options
					'merchant_name2'       => [
						'title'       => __( 'Merchant 2 Name', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Enter the name you want to use for the second merchant.', 'woocommerce' ),
						'default'     => __( 'Merchant 2', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'contact_email2'       => [
						'title'       => __( 'Contact Email', 'woocommerce' ) . '<br>(' . $this->merchant_name2 . ')',
						'type'        => 'text',
						'description' => __( 'Enter the email you want to use for the second merchant. This will be shown to the user in case there are problems with the transaction.', 'woocommerce' ),
						'default'     => __( 'email@sample.com', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_user2'     => [
						'title'       => __( 'PayPal API User ', 'woocommerce' ) . '<br>(' . $this->merchant_name2 . ')',
						'type'        => 'text',
						'description' => __( 'Enter the username for the second Payflow Gateway merchant account.', 'woocommerce' ),
						'default'     => __( 'user123', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_vendor2'   => [
						'title'       => __( 'PayPal API Vendor ', 'woocommerce' ) . '<br>(' . $this->merchant_name2 . ')',
						'type'        => 'text',
						'description' => __( 'Enter the vendor name for the second Payflow Gateway merchant account. If unsure, use the username.', 'woocommerce' ),
						'default'     => __( 'user123', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_partner2'  => [
						'title'       => __( 'PayPal API Partner ', 'woocommerce' ) . '<br>(' . $this->merchant_name2 . ')',
						'type'        => 'text',
						'description' => __( 'Enter the partner name for the second Payflow Gateway merchant account. If unsure, use "PayPal".', 'woocommerce' ),
						'default'     => __( 'PayPal', 'woocommerce' ),
						'desc_tip'    => true,
					],
					'paypal_api_password2' => [
						'title'       => __( 'PayPal API Password ', 'woocommerce' ) . '<br>(' . $this->merchant_name2 . ')',
						'type'        => 'password',
						'description' => __( 'Enter the password for the second Payflow Gateway merchant account.', 'woocommerce' ),
						'desc_tip'    => true,
					],
				];
			}

			public function process_payment( $order_id ) {
				global $woocommerce;

				try {
					$order = new WC_Order( $order_id );
				} catch ( Exception $e ) {
					wc_add_notice( 'Could not create order: ' . $e->getMessage() );

					return;
				}

				// Get customer input data from page
				$PaymentOption      = wp_kses_post( $_POST['credit_card_type'] );
				$creditCardNumber   = wp_kses_post( $_POST['credit_card_number'] ); //  Set this to the string entered as the credit card number on the Billing page
				$expDate            = wp_kses_post( $_POST['exp_month'] ) . wp_kses_post( $_POST['exp_year'] ); //  Set this to the credit card expiry date entered on the Billing page
				$cvv2               = wp_kses_post( $_POST['cvv'] ); //  Set this to the CVV2 string entered on the Billing page
				$firstName          = $order->get_billing_first_name(); //  Set this to the customer's first name that was entered on the Billing page
				$lastName           = $order->get_billing_last_name(); //  Set this to the customer's last name that was entered on the Billing page
				$street             = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(); //  Set this to the customer's street address that was entered on the Billing page
				$city               = $order->get_billing_city(); //  Set this to the customer's city that was entered on the Billing page
				$state              = $order->get_billing_state(); //  Set this to the customer's state that was entered on the Billing page
				$zip                = $order->get_billing_postcode(); //  Set this to the zip code of the customer's address that was entered on the Billing page
				$countryCode        = $order->get_billing_country(); //  Set this to the PayPal code for the Country of the customer's address that was entered on the Billing page
				$currencyCode       = $order->get_currency(); //  Set this to the PayPal code for the Currency used by the customer
				$donationAmount     = (double) wp_kses_post( $_POST['donation_amt'] ); // Custom for Hopcraft
				$finalPaymentAmount = $order->get_total();

				$orderDescription = 'Order #' . $order->get_id() . ': ' . $firstName . ' ' . $lastName . ' for ' . $finalPaymentAmount . ' ' . $currencyCode;
				if ( $donationAmount > 0 ) {
					$orderDescription .= ' with a donation of ' . $donationAmount . ' ' . $currencyCode;
				}

				//Calculate split pricing
				///////////////////////////////////////////////////////////////////////
				$merchant1_total = 0;
				$merchant2_total = 0;

				//Get products in order
				$items = $order->get_items();

				foreach ( $items as $item ) {
					$product = wc_get_product( $item['product_id'] );

					$price = $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price() * $item['qty'];

					if ( $product->get_type() === 'auction' ) {
						//All auction products go to Hopcraft Paypal account i.e. merchant1
						$merchant1_total += (float) $product->get_auction_max_bid();
					} else {

						//Get amount Merchant 1 will be paid
						$merchant1_amount = get_post_meta( $product->get_id(), 'merchant1_amount', true ) * $item['qty'];
						//Get amount Merchant 2 will be paid
						$merchant2_amount = get_post_meta( $product->get_id(), 'merchant2_amount', true ) * $item['qty'];

						if ( $price == $merchant1_amount + $merchant2_amount ) {
							//Product has both merchant amounts and adds up to total amount
							//add totals for payment processing
							$merchant1_total += $merchant1_amount;
							$merchant2_total += $merchant2_amount;

							//add additional donations to Merchant 2 total
							//Custom for Hopcraft
							$merchant2_total += $donationAmount;

						} else {
							wc_add_notice( __( 'Product with ID: ', 'woocommerce' ) . $product->get_id() . __( ' does not have proper pricing for split payments.', 'woocommerce' ) );

							return;
						}
					}

				}

				//end Calculate split pricing

				if ( $donationAmount > 0 ) {
					// If adding a donation, create a new fee for the Order

					$fee = new WC_Order_Item_Fee();
					$fee->set_name( 'Donation' );
					$fee->set_total( $donationAmount );
					$fee->set_taxes( [] );
					$order->add_item( $fee );
					$order->set_total( $order->get_total() + $donationAmount );

				}

				//Process payments here
				///////////////////////////////////////////////////////////////////////

				//Paypal Processing

				/**
				 * Payflow API Module
				 *
				 * Defines all the global variables and the wrapper functions
				 */

				$Env = $this->paypal_api_env == 'yes' ? 'pilot' : null;

				//'------------------------------------
				//' Payflow API Credentials
				//'------------------------------------
				$API_User      = $this->paypal_api_user;
				$API_Password  = $this->paypal_api_password;
				$API_Vendor    = $this->paypal_api_vendor;
				$API_Partner   = $this->paypal_api_partner;
				$API_User2     = $this->paypal_api_user2;
				$API_Password2 = $this->paypal_api_password2;
				$API_Vendor2   = $this->paypal_api_vendor2;
				$API_Partner2  = $this->paypal_api_partner2;

				if ( empty( $API_User )
				     || empty( $API_Password )
				     || empty( $API_Vendor )
				     || empty( $API_Partner )
				     || empty( $API_User2 )
				     || empty( $API_Password2 )
				     || empty( $API_Vendor2 )
				     || empty( $API_Partner2 )
				) {

					wc_add_notice( 'Could not complete transaction. API credentials are missing.' );

					return;
				}

				if ( ! function_exists( 'curl_init' ) ) {
					wc_add_notice( 'cURL must be installed on this server.' );

					return;
				}

				// BN Code
				$sBNCode = "PF-CCWizard";

				// Set API Endpoint from sandbox admin option
				if ( $Env == "pilot" ) {
					$API_Endpoint = "https://pilot-payflowpro.paypal.com";
				} else {
					$API_Endpoint = "https://payflowpro.paypal.com";
				}

				if ( session_id() == "" ) {
					session_start();
				}

				// End Payflow API Module
				/////////////////////////////////////////////////////////////////////////////////////

				if ( $PaymentOption == "Visa" || $PaymentOption == "MasterCard" || $PaymentOption == "Amex" || $PaymentOption == "Discover" ) {
					/*
					'------------------------------------
					' The paymentAmount is the total value of
					' the shopping cart, that was set
					' earlier in a session variable
					' by the shopping cart page
					'------------------------------------
					*/
					$creditCardType = $PaymentOption; //  Set this to one of the acceptable values (Visa/MasterCard/Amex/Discover) match it to what was selected on your Billing page

					$paymentType = "Authorization";

					/*
					'------------------------------------
					'
					' The DirectPayment function is defined in the file PayPalFunctions.php,
					' that is included at the top of this file.
					'-------------------------------------------------
					*/

					$payments_failed = false;
					$notes           = ''; // added to order status when things go wrong

					try {

						if ( $merchant1_total > 0 ) {
							//Transaction block for Merchant 1
							$merchant1_response = $this->DirectPayment( $paymentType,
								$merchant1_total,
								$creditCardType,
								$creditCardNumber,
								$expDate,
								$cvv2,
								$firstName,
								$lastName,
								$street,
								$city,
								$state,
								$zip,
								$countryCode,
								$currencyCode,
								$orderDescription,
								$API_Endpoint,
								$API_User,
								$API_Password,
								$API_Vendor,
								$API_Partner,
								$sBNCode );

							$ack = $merchant1_response["RESULT"];
							if ( $ack != "0" ) {
								$payments_failed = true;
								$notes           .= ' Transaction to ' . $this->merchant_name . ' failed. Server response: ';
								// See pages 50 through 65 in https://cms.paypal.com/cms_content/US/en_US/files/developer/PP_PayflowPro_Guide.pdf for a list of RESULT values (error codes)
								//Display a user friendly Error on the page using any of the following error information returned by Payflow
								$errors = '';
								foreach ( $merchant1_response as $code => $response ) {
									$notes  .= $code . ': ' . $response . '<br>';
									$errors .= $code . ': ' . $response . '<br>';
								}
								$output = '';
								$output .= __( 'Payment error ', 'woocommerce' );
								$output .= '(' . __( $this->merchant_name . ' Transaction', 'woocommerce' ) . ')';
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									$output .= '<br />';
									$output .= $errors;
								}
								wc_add_notice( $output );
							}
							//end merchant 1
						}

						if ( $merchant2_total > 0 ) {
							//Transaction block for Merchant 2
							$merchant2_response = $this->DirectPayment( $paymentType,
								$merchant2_total,
								$creditCardType,
								$creditCardNumber,
								$expDate,
								$cvv2,
								$firstName,
								$lastName,
								$street,
								$city,
								$state,
								$zip,
								$countryCode,
								$currencyCode,
								$orderDescription,
								$API_Endpoint,
								$API_User2,
								$API_Password2,
								$API_Vendor2,
								$API_Partner2,
								$sBNCode );
							$ack                = $merchant2_response["RESULT"];
							if ( $ack != "0" ) {
								$payments_failed = true;
								$notes           .= ' Transaction to ' . $this->merchant_name2 . ' failed. Server response: ';
								// See pages 50 through 65 in https://cms.paypal.com/cms_content/US/en_US/files/developer/PP_PayflowPro_Guide.pdf for a list of RESULT values (error codes)
								//Display a user friendly Error on the page using any of the following error information returned by Payflow
								$errors = '';
								foreach ( $merchant2_response as $code => $response ) {
									$notes  .= $code . ': ' . $response . '<br>';
									$errors .= $code . ': ' . $response . '<br>';
								}
								$output = '';
								$output .= __( 'Payment error ', 'woocommerce' );
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									$output .= '(' . __( $this->merchant_name2 . ' Transaction', 'woocommerce' ) . ')';
									$output .= '<br />';
									$output .= $errors;
								}
								wc_add_notice( $output );
							} // end merchant 2
						}

					} catch ( Exception $e ) {
						wc_add_notice( 'Could not complete transaction. Internal Server Error: ' . $e->getMessage() );

						return;
					}
					//end payment processing try/catch block

					if ( $payments_failed ) {
						// One or both payments failed, void transactions and notify user
						if ( $merchant1_response['RESULT'] == '0' ) {
							//First transaction succeeded so we need to void the transaction
							$void_result1 = $this->VoidTransaction(
								$merchant1_response['PNREF'],
								$API_Endpoint,
								$API_User,
								$API_Password,
								$API_Vendor,
								$API_Partner,
								$sBNCode
							);

							if ( $void_result1['RESULT'] != '0' ) {
								//error voiding transaction
								wc_add_notice( 'Voiding transaction failed. Please report this to our staff at ' . $this->contact_email1 );
								$notes .= ' Void transaction failed for ' . $this->merchant_name . '. ';
							}
						}

						if ( $merchant2_response['RESULT'] == '0' ) {
							//Second transaction failed
							$void_result2 = $this->VoidTransaction( $merchant2_response['PNREF'], $API_Endpoint, $API_User2, $API_Password2, $API_Vendor2, $API_Partner2, $sBNCode );

							if ( $void_result2['RESULT'] != '0' ) {
								//error voiding transaction
								wc_add_notice( 'Voiding transaction failed. Please report this to our staff at ' . $this->contact_email2 );
								$notes .= ' Void transaction failed for ' . $this->merchant_name2 . '. ';
							}
						}

						$order->update_status( 'pending' );
						$order->add_order_note( $notes );
						wc_add_notice( 'If you continue to have trouble with your order, please contact us at <a href="mailto:' . $this->support_email . '">' . $this->support_email . '</a>' );

						return;
					} else {
						// payments succeeded
						//Adding order notes for the transaction id's
						$order->add_order_note( $this->merchant_name . ' Transaction ID: ' . $merchant1_response['PNREF'] );
						$order->add_order_note( $this->merchant_name2 . ' Transaction ID: ' . $merchant2_response['PNREF'] );
					}
				}
				//end Paypal Processing

				// Mark as complete because payment succeeded
				$order->payment_complete();
				$order->update_status( 'completed' );

				// Reduce stock levels
				wc_reduce_stock_levels( $order_id );

				// Remove cart
				$woocommerce->cart->empty_cart();

				// Return thank you redirect
				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				];

			}

			/**
			 * DirectPayment: Prepares the parameters for direct payment (credit card) and makes the call.
			 *
			 * Note:
			 *      There are other optional inputs for credit card processing that are not presented here.
			 *      For a complete list of inputs available, please see the documentation here for US and UK:
			 *      http://www.paypal.com/en_US/pdf/PayflowPro_Guide.pdf
			 *      https://www.paypal.com/en_GB/pdf/PP_WebsitePaymentsPro_IntegrationGuide.pdf
			 *
			 * @param $paymentType string           paymentType has to be one of the following values: Sale or Order
			 * @param $paymentAmount string         Total value of the shopping cart
			 * @param $creditCardType  string       Credit card type has to one of the following values: Visa or MasterCard or Discover or Amex or Switch or Solo
			 * @param $creditCardNumber  string     Credit card number
			 * @param $expDate  string              Credit expiration date
			 * @param $cvv2 string                  CVV2
			 * @param $firstName  string            Customer's First Name
			 * @param $lastName  string             Customer's Last Name
			 * @param $street  string               Customer's Street Address
			 * @param $city  string                 Customer's City
			 * @param $state  string                Customer's State
			 * @param $zip  string                  Customer's Zip
			 * @param $countryCode  string          Customer's Country represented as a PayPal CountryCode
			 * @param $currencyCode  string         Customer's Currency represented as a PayPal CurrencyCode
			 * @param $orderdescription  string     Short textual description of the order
			 * @param $API_Endpoint  string
			 * @param $API_User string
			 * @param $API_Password string
			 * @param $API_Vendor string
			 * @param $API_Partner string
			 * @param $sBNCode string
			 *
			 * @return array|bool                   The NVP Collection object of the Response.
			 */
			public function DirectPayment(
				$paymentType,
				$paymentAmount,
				$creditCardType,
				$creditCardNumber,
				$expDate,
				$cvv2,
				$firstName,
				$lastName,
				$street,
				$city,
				$state,
				$zip,
				$countryCode,
				$currencyCode,
				$orderdescription,
				$API_Endpoint,
				$API_User,
				$API_Password,
				$API_Vendor,
				$API_Partner,
				$sBNCode
			) {
				// Construct the parameter string that describes the credit card payment
				$replaceme = array( "-", " " );
				$card_num  = str_replace( $replaceme, "", $creditCardNumber );

				$nvpstr = "&TENDER=C";
				if ( "Sale" == $paymentType ) {
					$nvpstr .= "&TRXTYPE=S";
				} elseif ( "Authorization" == $paymentType ) {
					$nvpstr .= "&TRXTYPE=A";
				} else //default to sale
				{
					$nvpstr .= "&TRXTYPE=S";
				}

				// Other information
				$ipaddr = $_SERVER['REMOTE_ADDR'];

				$nvpstr .= '&ACCT=' . $card_num . '&CVV2=' . $cvv2 . '&EXPDATE=' . $expDate . '&ACCTTYPE=' . $creditCardType . '&AMT=' . $paymentAmount . '&CURRENCY=' . $currencyCode;
				$nvpstr .= '&FIRSTNAME=' . $firstName . '&LASTNAME=' . $lastName . '&STREET=' . $street . '&CITY=' . $city . '&STATE=' . $state . '&ZIP=' . $zip . '&COUNTRY=' . $countryCode;
				$nvpstr .= '&CLIENTIP=' . $ipaddr . '&ORDERDESC=' . $orderdescription;
				// Transaction results (especially values for declines and error conditions) returned by each PayPal-supported
				// processor vary in detail level and in format. The Payflow Verbosity parameter enables you to control the kind
				// and level of information you want returned.
				// By default, Verbosity is set to LOW. A LOW setting causes PayPal to normalize the transaction result values.
				// Normalizing the values limits them to a standardized set of values and simplifies the process of integrating
				// the Payflow SDK.
				// By setting Verbosity to MEDIUM, you can view the processor's raw response values. This setting is more �verbose�
				// than the LOW setting in that it returns more detailed, processor-specific information.
				// Review the chapter in the Developer's Guides regarding VERBOSITY and the INQUIRY function for more details.
				// Set the transaction verbosity to MEDIUM.
				$nvpstr .= '&VERBOSITY=HIGH';

				// The $unique_id field is storing our unique id that we'll use in the request id header.
				$unique_id = date( 'ymd-H' ) . rand( 1000, 9999 );

				/*'-------------------------------------------------------------------------------------------
				' Make the call to Payflow to finalize payment
				' If an error occured, show the resulting errors
				'-------------------------------------------------------------------------------------------
				*/
				$resArray = $this->hash_call( $nvpstr, $unique_id, $API_Endpoint, $API_User, $API_Password, $API_Vendor, $API_Partner, $sBNCode );

				return $resArray;
			}

			public function VoidTransaction(
				$pnref,
				$API_Endpoint,
				$API_User,
				$API_Password,
				$API_Vendor,
				$API_Partner,
				$sBNCode
			) {

				$nvpstr = "&TENDER=C&TRXTYPE=V&ORIGID=" . $pnref;

				// Other information
				$ipaddr = $_SERVER['REMOTE_ADDR'];

				$nvpstr .= '&CLIENTIP=' . $ipaddr;
				// Transaction results (especially values for declines and error conditions) returned by each PayPal-supported
				// processor vary in detail level and in format. The Payflow Verbosity parameter enables you to control the kind
				// and level of information you want returned.
				// By default, Verbosity is set to LOW. A LOW setting causes PayPal to normalize the transaction result values.
				// Normalizing the values limits them to a standardized set of values and simplifies the process of integrating
				// the Payflow SDK.
				// By setting Verbosity to MEDIUM, you can view the processor's raw response values. This setting is more �verbose�
				// than the LOW setting in that it returns more detailed, processor-specific information.
				// Review the chapter in the Developer's Guides regarding VERBOSITY and the INQUIRY function for more details.
				// Set the transaction verbosity to MEDIUM.
				$nvpstr .= '&VERBOSITY=HIGH';

				// The $unique_id field is storing our unique id that we'll use in the request id header.
				$unique_id = date( 'ymd-H' ) . rand( 1000, 9999 );

				/*'-------------------------------------------------------------------------------------------
				' Make the call to Payflow to finalize payment
				' If an error occured, show the resulting errors
				'-------------------------------------------------------------------------------------------
				*/
				$resArray = $this->hash_call( $nvpstr, $unique_id, $API_Endpoint, $API_User, $API_Password, $API_Vendor, $API_Partner, $sBNCode );

				return $resArray;
			}

			/**
			 * -------------------------------------------------------------------------------------------------------------------------------------------
			 * hash_call: Function to perform the API call to Payflow
			 * @nvpStr is nvp string.
			 * returns an associtive array containing the response from the server.
			 * -------------------------------------------------------------------------------------------------------------------------------------------
			 */
			function hash_call( $nvpStr, $unique_id, $API_Endpoint, $API_User, $API_Password, $API_Vendor, $API_Partner, $sBNCode ) {

				$headers[] = "Content-Type: text/namevalue";
				// Set the server timeout value to 45, but notice below in the cURL section, the timeout
				// for cURL is set to 90 seconds.  Make sure the server timeout is less than the connection.
				$headers[] = "X-VPS-CLIENT-TIMEOUT: 45";
				$headers[] = "X-VPS-REQUEST-ID:" . $unique_id;

				//setting the curl parameters.
				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_URL, $API_Endpoint );
				curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
				curl_setopt( $ch, CURLOPT_VERBOSE, 1 );

				//turning off the server and peer verification(TrustManager Concept).
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );

				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 90 );        // times out after 90 secs
				curl_setopt( $ch, CURLOPT_POST, 1 );

				//NVPRequest for submitting to server
				$nvpreq = "USER=" . $API_User . '&VENDOR=' . $API_Vendor . '&PARTNER=' . $API_Partner . '&PWD=' . $API_Password . $nvpStr . "&BUTTONSOURCE=" . urlencode( $sBNCode );

				//setting the nvpreq as POST FIELD to curl
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $nvpreq );

				//getting response from server
				$response = curl_exec( $ch );

				//converting NVPResponse to an Associative Array
				$nvpResArray = $this->deformatNVP( $response );

				if ( curl_errno( $ch ) ) {
					// moving to display page to display curl errors
					//$_SESSION['curl_error_no']=curl_errno($ch) ;
					//$_SESSION['curl_error_msg']=curl_error($ch);

					//Execute the Error handling module to display errors.
					wc_add_notice( 'Error connecting to PayPal: ' . curl_errno( $ch ) . ' ' . curl_error( $ch ) );

					return false;
				} else {
					//closing the curl
					curl_close( $ch );
				}

				return $nvpResArray;
			}

			/**----------------------------------------------------------------------------------
			 * This function will take NVPString and convert it to an Associative Array and it will decode the response.
			 * It is usefull to search for a particular key and displaying arrays.
			 * @nvpstr is NVPString.
			 * @nvpArray is Associative Array.
			 * ----------------------------------------------------------------------------------
			 */
			public function deformatNVP( $nvpstr ) {
				$intial   = 0;
				$nvpArray = array();

				while ( strlen( $nvpstr ) ) {
					//postion of Key
					$keypos = strpos( $nvpstr, '=' );
					//position of value
					$valuepos = strpos( $nvpstr, '&' ) ? strpos( $nvpstr, '&' ) : strlen( $nvpstr );

					/*getting the Key and Value values and storing in a Associative Array*/
					$keyval = substr( $nvpstr, $intial, $keypos );
					$valval = substr( $nvpstr, $keypos + 1, $valuepos - $keypos - 1 );
					//decoding the respose
					$nvpArray[ urldecode( $keyval ) ] = urldecode( $valval );
					$nvpstr                           = substr( $nvpstr, $valuepos + 1, strlen( $nvpstr ) );
				}

				return $nvpArray;
			}

			/**
			 * Payment Fields
			 *
			 * Outputs HTML for the payment fields form
			 * @return string
			 */
			public function payment_fields() { ?>

                <div class="<?= NW_PAYMENT_GATEWAY_ID; ?>-payment-form flex-container">
                    <div class="flex-50 flex-container">
                        <div class="input-group">
                            <label for="donation_amt">If you would like to make an additional donation please enter the
                                amount here</label>
                            <input name="donation_amt"
                                   id="donation_amt"
                                   type="number"
                                   step="0.01"
                                   placeholder="$XXX.XX"
								<?php if ( isset( $_SESSION['donation_amt'] ) ): ?>
                                    value="<?= $_SESSION['donation_amt']; ?>"
								<?php endif; ?>
                            />
                        </div>
                        <div class="input-group flex-50">
                            <label>Credit Card Type<span class="required">*</span>
                                <select name="credit_card_type">
                                    <option value="Visa">Visa</option>
                                    <option value="MasterCard">Master Card</option>
                                    <option value="Amex">American Express</option>
                                    <option value="Discover">Discover</option>
                                </select>
                            </label>
                        </div>
                        <div class="input-group flex-50">
                            <label for="credit_card_number">Credit Card Number<span class="required">*</span></label>
                            <input name="credit_card_number"
                                   id="credit_card_number"
                                   type="text"
                            />
                        </div>
                        <div class="input-group flex-33">
                            <label for="exp_month">Month<span class="required">*</span> (MM)</label>
                            <input name="exp_month"
                                   id="exp_month"
                                   type="text"
                            />
                        </div>
                        <div class="input-group flex-33">
                            <label for="exp_year">Year<span class="required">*</span> (YYYY)</label>
                            <input name="exp_year"
                                   id="exp_year"
                                   type="text"
                            />
                        </div>
                        <div class="input-group flex-33">
                            <label for="cvv">CVV Number<span class="required">*</span></label>
                            <input name="cvv"
                                   id="cvv"
                                   type="text"
                            />
                        </div>
                    </div>
                </div>

				<?php
			}

			public function validate_fields() {
				//Check form validation here
				//If validation passes, return true
				//If validation fails, return false
				// Use the wc_add_notice() function for errors displayed to the user

				$valid = true;

				$fields = [
					'donation_amt'       => [
						'label' => 'Donation Amount',
						'value' => $_POST['donation_amt'],
					],
					'credit_card_type'   => [
						'label' => 'Credit Card Type',
						'value' => $_POST['credit_card_type'],
					],
					'credit_card_number' => [
						'label' => 'Credit Card Number',
						'value' => $_POST['credit_card_number'],
					],
					'exp_month'          => [
						'label' => 'Expiration Month',
						'value' => $_POST['exp_month'],
					],
					'exp_year'           => [
						'label' => 'Expiration Year',
						'value' => $_POST['exp_year'],
					],
					'cvv'                => [
						'label' => 'CVV Number',
						'value' => $_POST['cvv'],
					],
				];

				$required = [
					'credit_card_type',
					'credit_card_number',
					'exp_month',
					'exp_year',
					'cvv',
				];

				foreach ( $fields as $field => $data ) {
					if ( in_array( $field, $required ) && empty( $data['value'] ) ) {
						//If field is required and empty, add notice and set valid to false
						wc_add_notice( $data['label'] . __( ' is required.', 'woocommerce' ) );
						$valid = false;
					}
				}

				return $valid;

			}
		}
	}
}

add_filter( 'woocommerce_payment_gateways', 'nw_add_split_payments_gateway' );
if ( ! function_exists( 'nw_add_split_payments_gateway' ) ) {
	function nw_add_split_payments_gateway() {
		$methods[] = 'WC_Gateway_NW_Split_Payments_Gateway';

		return $methods;
	}
}
