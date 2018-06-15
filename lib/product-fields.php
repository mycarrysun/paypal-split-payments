<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

//Add custom fields to product pages
add_action( 'woocommerce_product_options_pricing', 'nw_split_payments_product_fields' );
if ( ! function_exists( 'nw_split_payments_product_fields' ) ) {
	function nw_split_payments_product_fields() {
		$settings = get_option( 'woocommerce_' . NW_PAYMENT_GATEWAY_ID . '_settings' );

		//Set Merchant 1 Name
		$merchant_name = isset( $settings['merchant_name'] ) && ! empty( $settings['merchant_name'] ) ? $settings['merchant_name'] : 'Merchant 1';

		// Set Merchant 2 Name
		$merchant_name2 = isset( $settings['merchant_name2'] ) && ! empty( $settings['merchant_name2'] ) ? $settings['merchant_name2'] : 'Merchant 2';

		woocommerce_wp_text_input( [
			'id'    => 'merchant1_amount',
			'class' => 'wc_input_price_short',
			'label' => $merchant_name . __( ' Amount', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
		] );
		woocommerce_wp_text_input( [
			'id'    => 'merchant2_amount',
			'class' => 'wc_input_price_short',
			'label' => $merchant_name2 . __( ' Amount', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
		] );
	}
}

// Save fields on post update
add_action( 'save_post', 'nw_split_payments_product_save_fields' );
if ( ! function_exists( 'nw_split_payments_product_save_fields' ) ) {
	function nw_split_payments_product_save_fields( $product_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$merchant_amt_1 = isset( $_POST['merchant1_amount'] ) ? (double) $_POST['merchant1_amount'] : 0;
		$merchant_amt_2 = isset( $_POST['merchant2_amount'] ) ? (double) $_POST['merchant2_amount'] : 0;

		$product = wc_get_product( $product_id );

		if ( (double) $product->get_regular_price() !== ( $merchant_amt_1 + $merchant_amt_2 ) ) {
			nw_add_error_to_session( 'Merchant prices do not add up to the Regular Price! Please fix before saving.' );

			return;
		}

		// Save Merchant 1 Amount
		if ( $merchant_amt_1 >= 0 ) {
			update_post_meta( $product_id, 'merchant1_amount', $merchant_amt_1 );
		} else {
			delete_post_meta( $product_id, 'merchant1_amount' );
		}

		// Save Merchant 2 Amount
		if ( $merchant_amt_2 >= 0 ) {
			update_post_meta( $product_id, 'merchant2_amount', $merchant_amt_2 );
		} else {
			delete_post_meta( $product_id, 'merchant2_amount' );
		}
	}
}