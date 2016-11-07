<?php

if( ! class_exists( 'WPEC_Product_Licensing_Updater' ) ) {
	// load our custom updater
	include( dirname( __FILE__ ) . '/WPEC_Product_Licensing_Updater.php' );
}
function wpec_gold_cart_plugin_updater() {
	// retrieve our license key from the DB
	$license_key = 'b8cb6359b1d2544059586cdc58852860';
	// setup the updater
	$wpec_updater = new WPEC_Product_Licensing_Updater( 'http://dev.devsource.co', __FILE__, array(
			'version' 	=> '0.9', 				// current version number
			'license' 	=> $license_key, 		// license key (used get_option above to retrieve from DB)
			'item_id' 	=> 278 	// Product ID as per the website
		)
	);
}
add_action( 'admin_init', 'wpec_gold_cart_plugin_updater', 0 );