<?php

function wpec_licensing_admin_scripts() {
	$screen = get_current_screen();

	if ( ! is_object( $screen ) ) {
		return;
	}

	wp_enqueue_script( 'wpec-license-admin', plugins_url( '/js/wpec-lic-admin.js', WPEC_LICENSING_PLUGIN_FILE ), array( 'jquery' ) );
	wp_enqueue_script( 'jquery-ui-datepicker' );
	
	wp_localize_script( 'wpec-license-admin', 'license_vars', array(
			'license_member_nonce'	=> wp_create_nonce( 'license_member_nonce' ),
			'license_product_nonce'	=> wp_create_nonce( 'license_product_nonce' ), 
			'missing_username'		=> __( 'You must choose a username', 'rcp' )
		)
	);
	
	wp_enqueue_style( 'datepicker',  WPEC_LICENSING_PLUGIN_URL . '/css/datepicker.css' );

}
add_action( 'admin_enqueue_scripts', 'wpec_licensing_admin_scripts' );

