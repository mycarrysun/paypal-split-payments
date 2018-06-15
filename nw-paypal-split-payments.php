<?php

/*

Plugin Name: PayPal Split Payments
Plugin URI:
Description: Controls the checkout payment process and divides the payment of products into 2 merchant accounts.
Version: 1.1
Author: Mike Harrison
Author URI: https://nextwebtoday.com/vision#owner

*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Check if WooCommerce is active
 **/
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

define( 'NW_ROOT_PATH', plugins_url() . '/' . plugin_basename( dirname( __FILE__ ) ) );
define( 'NW_PLUGIN_PATH', plugin_basename( __FILE__ ) );
define( 'NW_PAYMENT_GATEWAY_ID', 'nw_split_payments' );

require_once( 'lib/show-error.php' ); //Shows an error in WP admin UI
require_once( 'lib/payment-gateway.php' ); //Require the payment gateway library
require_once( 'lib/product-fields.php' ); //Require hooks for product fields

add_filter( 'plugin_action_links_' . NW_PLUGIN_PATH, 'nw_split_payments_gateway_action_links' );
if ( ! function_exists( 'nw_split_payments_gateway_action_links' ) ) {
	function nw_split_payments_gateway_action_links( $links ) {

		$plugin_links = [
			'<a href="' . admin_url( "admin.php?page=wc-settings&tab=checkout&section=" . NW_PAYMENT_GATEWAY_ID ) . '">'
			. __( "Settings" ) . '</a>',
		];

		return array_merge( $plugin_links, $links );
	}
}

add_action( 'wp_enqueue_scripts', 'nw_split_payments_enqueue_scripts' );
if ( ! function_exists( 'nw_split_payments_enqueue_scripts' ) ) {
	function nw_split_payments_enqueue_scripts() {
		if ( get_the_ID() == get_option( 'woocommerce_checkout_page_id' ) ) {
			// We are on the checkout page
			wp_enqueue_style( 'nw-split-payments', NW_ROOT_PATH . '/css/style.min.css' );
		}
	}
}

//show errors from the session in the admin dashboard
add_action( 'admin_notices', 'nw_show_errors' );

//start session
if ( ! session_id() ) {
	session_start();
}