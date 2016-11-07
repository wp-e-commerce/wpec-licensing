<?php
//Add menu link in nav
function wpec_licenses_menu_tab ( $tabs ) {
	$tabs['license_keys'] = 'View Licenses';
	return $tabs;
}
add_filter( 'wpsc_user_profile_tabs', 'wpec_licenses_menu_tab' );

//Show the page temmplate
function _wpsc_action_license_keys_section() {
	global $products;

	$items = array();
	if ( _wpsc_has_license_keys() ) {
		foreach ( $products as $key => $file ) {
			$item = array();
			$item['email'] 		= esc_html( $file['email'] );
			$item['key'] 		= esc_html( $file['license_key'] );
			$item['active']		= $file['active'];
			$item['purchaseid'] = (int) $file['purchase_id'];

			$items[] = (object) $item;
		}
	}

	include( WPEC_LICENSING_PLUGIN_DIR . '/templates/account_page.php' );
}

add_action( 'wpsc_user_profile_section_license_keys', '_wpsc_action_license_keys_section' );

//Checks if user has licenses and are paid for.
function _wpsc_has_license_keys() {
	global $wpdb, $products,  $current_user;

	$sql = "SELECT * FROM {$wpdb->prefix}wpec_licensing_licenses WHERE `user_id` = '". $current_user->ID ."' ORDER BY `purchase_date` DESC";
	$products = $wpdb->get_results( $sql, ARRAY_A );

	if ( ! empty( $products ) && count( $products ) >= 1 ) {
		return true;
	} else {
		return false;
	}
}

function wpsc_user_license_keys() {
	global $products;

	foreach ( (array) $products as $purchase ) {
		
		
		//$dl_links = wpec_api_get_latest_download_file( $purchase['product_id'] );

		$purchase['active'] = ( $purchase['active'] == '1' ) ? 'Yes' : 'No';
		$purchase['expiration'] = $purchase['expiration_date'];
		
		echo "<table class='customer_details'>";
		echo "  <tr><td>" . __( 'Product', 'wpsc' ) . ":</td><td>" . $purchase['product_name'] . "</td></tr>";
		//echo "  <tr><td>" . __( 'Purchase #', 'wpsc' ) . ":</td><td>" . $purchase['purchase_id'] . "</td></tr>";
		echo "  <tr><td>" . __( 'License Key', 'wpsc' ) . ":</td><td>" . $purchase['license_key'] . "</td></tr>";
		echo "  <tr><td>" . __( 'Active', 'wpsc' ) . ":</td><td>" . $purchase['active'] . "</td></tr>";
		echo "  <tr><td>" . __( 'Expiration', 'wpsc' ) . ":</td><td>" . $purchase['expiration'] . "</a></td></tr>";
		//echo "  <tr><td>" . __( 'Support Tokens', 'wpsc' ) . ":</td><td>" . $purchase['tokens'] . "</td></tr>";
		//echo "- <a href=" . $renew_link . ">" . __( 'Renew License', 'wpsc') . "</a>";
		echo "</table>";
	}
}

/* Retrieve the download link for the most recent file for a product.
 *
 */
 function wpec_api_get_latest_download_file( $product_id ) {
	 global $wpdb;
	 
	 $file_id = get_post_meta( $product_id, '_wpec_current_file_id', true );
}

function wpec_lic_get_renewal_link( $license ) {
	
	if( ! wpec_lic_renewals_enabled() ) {
		return;
	}
	
	$license_data = wpec_lic_get_license_by_key( $license );
	
	$lic_status = wpec_lic_get_license_status( $license );
	
	if( $lic_status == 'expired' && $license_data['active'] == '1' ) {
		$renew_link = add_query_arg( array( 'wpec_lic_license_key' => $license ), get_option( 'shopping_cart_url' ) ) ;
		
		
	}
		
	
}
?>