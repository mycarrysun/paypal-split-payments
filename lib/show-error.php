<?php

if ( ! function_exists( 'nw_add_error_to_session' ) ) {
	function nw_add_error_to_session( $error ) {
		if ( session_id() === null ) {
			session_start();
		}

		$_SESSION['nw_errors'][] = $error;
	}
}

if ( ! function_exists( 'nw_show_error' ) ) {
	function nw_show_errors() {
		if ( isset( $_SESSION['nw_errors'] ) && count( $_SESSION['nw_errors'] ) ):
			foreach ( $_SESSION['nw_errors'] as $error ): ?>
                <div class="notice notice-error">
                    <p><?= __( $error, 'nw-paypal-split-payments' ) ?></p>
                </div>
			<?php
			endforeach;
		endif;

		unset( $_SESSION['nw_errors'] );
	}
}