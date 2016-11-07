<?php
function wpec_licenses_admin_menu() {
   add_menu_page(__( 'License Key Manager','wpec_license' ), __( 'License Key Manager','wpec_license' ), 'manage_options', 'wpec_license', 'wpec_licenses_html_page');
}
add_action('admin_menu', 'wpec_licenses_admin_menu');

function wpsc_licensing_register_settings( $settings_page ) {

	// Load required class
	require_once 'class.wpsc-settings-tab-licensing.php';

	// Register the tab
	$settings_page->register_tab( 'wpec_licensing', 'Licensing' );

}
// Create Licensing settings tab
add_action( 'wpsc_register_settings_tabs', 'wpsc_licensing_register_settings', 10, 1 );

function wpec_licenses_html_page() {
	
	?>
	
	<div class="wrap">
	
		<style>
			.column-status, .column-count { width: 100px; }
			.column-limit { width: 150px; }
		</style>
		<form id="licenses-filter" method="get">

			<input type="hidden" name="page" value="wpec_license" />
			<?php
			$license_table = new WPEC_Licenses_list_table();
			$license_table->prepare_items();
			$license_table->search_box( 'search', 'wpec_lic_search' );
			$license_table->views();
			$license_table->display();
			?>
		</form>	
	</div>
	<?php

	$redirect = get_transient( '_wpec_lic_bulk_actions_redirect' );

	if( false !== $redirect ) : delete_transient( '_wpec_lic_bulk_actions_redirect' ) ?>
	<script type="text/javascript">
	window.location = "<?php echo admin_url( 'admin.php?page=wpec_license' ); ?>";
	</script>
	<?php endif; ?>
	
	<!-- Add new license for members -->
	<h3><?php _e('Add New license (for existing user)', 'wpec_license'); ?></h3>
	<form id="add-new-license" action="" method="post">
		<table class="form-table">
			<tbody>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="license-username"><?php _e('Username', 'wpec_license'); ?></label>
					</th>
					<td>
						<input type="hidden" name="user_id" id="license-user-id" value=""/>
						<input type="text" name="user" id="license-user-name" autocomplete="off" class="regular-text license-user-search" style="width: 120px;"/>
						<img class="license-ajax-user waiting" src="<?php echo admin_url('images/wpspin_light.gif'); ?>" style="display: none;"/>
						<div id="license_user_search_results"></div>
						<p class="description"><?php _e('Begin typing the user name to add a license to.', 'wpec_license'); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="license-product"><?php _e('Licensed Product', 'wpec_license'); ?></label>
					</th>
					<td>
						<input type="hidden" name="product_id" id="license-product-id" value=""/>
						<input type="text" name="product_name" id="license-product-name" autocomplete="off" class="regular-text license-product-search" style="width: 200px;"/>
						<img class="license-ajax-product waiting" src="<?php echo admin_url('images/wpspin_light.gif'); ?>" style="display: none;"/>
						<div id="license_product_search_results"></div>
						<p class="description"><?php _e('Begin typing the Product name.', 'wpec_license'); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="license-product"><?php _e('Purchase date', 'wpec_license'); ?></label>
					</th>
					<td>
						<input type="text" name="product_purchase" id="license-product-purchase" class="license-datepicker" style="width: 120px;"/>
						<p class="description"><?php _e('Product purchase date. Format yyyy-mm-dd. Leave empty for today', 'wpec_license'); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
			<input type="hidden" name="license-action" value="add-manual-license"/>
		<p class="submit">
			<input type="submit" value="<?php _e('Add User License', 'wpec_license'); ?>" class="button-primary"/>
		</p>
	</form>
	<!-- End add new subscription for members -->
	<?php
}

function _wpsc_license_get_transaction_id ( $purchase ) {
	global $wpdb;
	
	//search in current purchase logs and if no match found search in legacy sales logs.
	$transact_id = $wpdb->get_var( $wpdb->prepare( 'SELECT transactid FROM '.$wpdb->prefix.'wpsc_purchase_logs WHERE id = %s AND processed > 2', $purchase ) );

	return $transact_id;
}


function _wpsc_admin_license_notices_updated ( $message ) {
	echo "<div id='wpsc-warning' class='updated'>". $message ."</div>";
}

function wpec_ajax_license_change_limit() {
	global $wpdb;

	if( ! isset( $_POST['license'] ) ) {
		status_header( 404 );
		die();
	}

	if( ! current_user_can( 'manage_options' ) ) {
		status_header( 415 );
		die();
	}

	// Grab the license ID and make sure its an int
	$license_id  = intval( $_POST['license'] );

	$limit = $wpdb->get_var( $wpdb->prepare( "SELECT active_limit FROM {$wpdb->prefix}wpec_licensing_licenses WHERE id = '%d' LIMIT 1", $license_id ) );
	
	$action = esc_html( $_POST['todo'] );
	
	if( $limit == 'unlimited' ) {
		$limit = 'unlimited';
	} else {
		if ( $action === 'increase' ) {
			$limit++;
		} else {
			$limit--;
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpec_licensing_licenses SET active_limit = '%d' WHERE `id` = '%d' LIMIT 1", $limit, $license_id ) );
	}
	
	if( $limit > 0 ) {
		echo $limit;
	} else {
		echo __( 'Unlimited', 'wpec-licensing' );
	}
	exit;
}
add_action( 'wp_ajax_wpec_license_change_limit', 'wpec_ajax_license_change_limit' );

?>