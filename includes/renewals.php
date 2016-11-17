<?php
function wpec_lic_renewals_enabled() {
	$settings = get_option( 'wpec_licensing' );

	$ret = isset( $settings['enable_renewals'] ) ? true : false;

	return $ret;
}

function wpec_keys_renewal_form() {

	if( ! wpec_lic_renewals_enabled() ) {
		return;
	}

	$renewal_keys = wpsc_get_customer_meta( 'wpec_keys_license_key' );
	$preset_key   = ! empty( $_GET['license_key'] ) ? esc_html( urldecode( $_GET['license_key'] ) ) : '';
	$error        = ! empty( $_GET['wpec-sl-error'] ) ? sanitize_text_field( $_GET['wpec-sl-error'] ) : '';

	ob_start(); ?>

	<form method="post" id="wpec_keys_renewal_form">
		<fieldset id="wpec_keys_renewal_fields">
			<p id="wpec_keys_show_renewal_form_wrap">
				<?php _e( 'Renewing a license key? <a href="#" id="wpec_keys_show_renewal_form">Click to renew an existing license</a>', 'wpsc' ); ?>
			</p>
			<p id="wpec-license-key-container-wrap" class="wpec-cart-adjustment" style="display:none;">
				<span class="edd-description"><?php _e( 'Enter the license key you wish to renew. Leave blank to purchase a new one.', 'wpsc' ); ?></span>
				<input class="edd-input required" type="text" name="wpec_keys_license_key" autocomplete="off" placeholder="<?php _e( 'Enter your license key', 'wpsc' ); ?>" id="wpec_keys_license_key" value="<?php echo $preset_key; ?>"/>
				<input type="hidden" name="wpec_lic_action" value="apply_license_renewal"/>
			</p>
			<p class="wpec-sl-renewal-actions" style="display:none">
				<input type="submit" id="wpec-add-license-renewal" disabled="disabled" class="edd-submit button blue button" value="<?php _e( 'Apply License Renewal', 'wpsc' ); ?>"/>&nbsp;<span><a href="#" id="wpec-cancel-license-renewal"><?php _e( 'Cancel', 'edd_sl' ); ?></a></span>
			</p>

			<?php if( ! empty( $renewal_keys ) ) : ?>
				<p id="wpec-license-key-container-wrap" class="wpec-cart-adjustment">
					<label class="edd-label" for="wpec-license-key">
						<?php _e( 'License key being renewed:', 'wpsc' ); ?>
					</label>
						<span class="edd-renewing-key-title"><?php echo wpsc_get_customer_meta( 'wpec_keys_license_product' ); ?></span>
						<span class="edd-renewing-key-sep">&nbsp;&ndash;&nbsp;</span>
						<span class="edd-renewing-key"><?php echo wpsc_get_customer_meta( 'wpec_keys_license_key' ); ?></span><br/>
				</p>
			<?php endif; ?>
		</fieldset>
		<?php if( ! empty( $error ) ) : ?>
			<div class="edd_errors">
				<p class="edd_error"><?php echo urldecode( $_GET['message'] ); ?></p>
			</div>
		<?php endif; ?>
	</form>
	<?php if( ! empty( $renewal_keys ) ) : ?>
	<form method="post" id="wpec_cancel_renewal_form">
		<p>
			<input type="hidden" name="wpec_lic_action" value="cancel_license_renewal"/>
			<input type="submit" class="wpec-submit button" value="<?php _e( 'Cancel License Renewal', 'wpsc' ); ?>"/>
		</p>
	</form>
	<?php
	endif;
	echo ob_get_clean();
}
add_action('wpsc_before_shipping_of_shopping_cart', 'wpec_keys_renewal_form', -1);

function wpec_keys_listen_for_renewal_checkout() {
	global $wpdb;

	if( empty( $_REQUEST['wpec_lic_license_key'] ) ) {
		return;
	}

	$license_key = sanitize_text_field( $_REQUEST['wpec_lic_license_key'] );
	$added = wpec_lic_add_renewal_to_cart( sanitize_text_field( $license_key ), true );

	if( $added && ! is_wp_error( $added ) ) {
		$lic_data = wpec_lic_get_license_by_key( $license_key );
		wpsc_update_customer_meta( 'wpec_keys_license_key', $license_key );
		wpsc_update_customer_meta( 'wpec_keys_license_product', $lic_data['product_name'] );
		$redirect = get_option( 'checkout_url' );
	} else {

		$code     = $added->get_error_code();
		$message  = $added->get_error_message();
		$redirect = add_query_arg( array( 'wpec-sl-error' => $code, 'message' => urlencode( $message ) ), get_option( 'checkout_url' ) );
	}

	wp_safe_redirect( $redirect ); exit;

}
add_action( 'template_redirect', 'wpec_keys_listen_for_renewal_checkout' );

function wpec_lic_apply_license_renewal( $data ) {

	if( ! wpec_lic_renewals_enabled() ) {
		return;
	}

	$license  = ! empty( $data['wpec_keys_license_key'] ) ? sanitize_text_field( $data['wpec_keys_license_key'] ) : false;
	$added    = wpec_lic_add_renewal_to_cart( $license, true );

	if( $added && ! is_wp_error( $added ) ) {
		$license_data = wpec_lic_get_license_by_key( $license );

		wpsc_update_customer_meta( 'wpec_keys_license_key', $license );
		wpsc_update_customer_meta( 'wpec_keys_license_product', $license_data['product_name'] );

		$redirect = get_option( 'checkout_url' );

	} else {

		$code     = $added->get_error_code();
		$message  = $added->get_error_message();
		$redirect = add_query_arg( array( 'wpec-sl-error' => $code, 'message' => urlencode( $message ) ), get_option( 'checkout_url' ) );

	}

	wp_safe_redirect( $redirect ); exit;
}
add_action( 'wpec_lic_apply_license_renewal', 'wpec_lic_apply_license_renewal' );

function wpec_lic_cancel_license_renewal() {

	if( ! wpec_lic_renewals_enabled() ) {
		return;
	}

	wpsc_delete_customer_meta( 'wpec_keys_renewal_key' );
	wpsc_delete_customer_meta( 'wpec_keys_license_key' );
	wpsc_delete_customer_meta( 'wpec_keys_is_renewal' );


	wp_safe_redirect( get_option( 'checkout_url' ) );
	exit;
}
add_action( 'wpec_lic_cancel_license_renewal', 'wpec_lic_cancel_license_renewal' );




/**
 * Adds a license key renewal to the cart
 *
 * @since  1.0
 * @param  integer       $license_id The ID of the license key to add
 * @param  bool          $by_key     Set to true if passing actual license key as $license_id
 * @return bool|WP_Error $success    True if the renewal was added to the cart, WP_Error is not successful
 */
function wpec_lic_add_renewal_to_cart( $license_id = 0, $by_key = false ) {
	global $wpsc_cart;

	if( $by_key ) {
		$license_key = $license_id;
		$license = wpec_lic_get_license_by_key( $license_id );
		$license_id = $license['id'];

	} else {
		$license = wpec_lic_get_license_by_key( $license_id );
		$license_key = $license['license_key'];
	}

	if( empty( $license_id ) ) {
		return new WP_Error( 'missing_license', __( 'No license ID supplied or invalid key provided', 'edd_sl' ) );
	}

	if ( ! $license ) {
		return new WP_Error( 'missing_license', __( 'No license ID supplied or invalid key provided', 'edd_sl' ) );
	}

	$success     = false;
	$payment_id  = $license['purchase_id'];
	$purchase_log = new WPSC_Purchase_Log( $payment_id );
	$product_id = $license['product_id'];
	$product    = get_post( $product_id );

	if( ! $purchase_log->is_transaction_completed() ) {
		return new WP_Error( 'payment_not_complete', __( 'The purchase record for this license is not marked as complete', 'edd_sl' ) );
	}

	if ( '1' !== $license['active'] ) {
		return new WP_Error( 'license_disabled', __( 'The supplied license has been disabled and cannot be renewed', 'edd_sl' ) );
	}

	if ( ! in_array( $product->post_status, array( 'publish', 'inherit' ) ) ) {
		return new WP_Error( 'license_disabled', __( 'The product for this license is not published', 'edd_sl' ) );
	}

	$parameters = array( 'quantity' => '1' );

	// Make sure it's not already in the cart
	foreach ( (array) $wpsc_cart->cart_items as $position => $cart_item ) {
		if ( (int)$cart_item->product_id == (int)$product_id ) {
			$wpsc_cart->remove_item( $position );
			continue;
		}
	}

	// Check if product has variations and if it does pass the correct $parameters to the cart
	$parent_id = wpsc_product_is_variation( $product_id );

	if ( $parent_id ) {
		// This is a variation of a product
		// Get variation term id and pass that to the cart
		$parameters['variation_values'] = wp_list_pluck( wp_get_object_terms( $product_id, 'wpsc-variation' ), 'term_id' );
	}


	// Confirm item was added to cart successfully
	if( ! $wpsc_cart->set_item( $product_id, $parameters ) ) {
		return new WP_Error( 'not_in_cart', __( 'The download for this license is not in the cart or could not be added', 'edd_sl' ) );
	}

	$success = true;

	if( true === $success ) {

		wpsc_update_customer_meta( 'wpec_keys_is_renewal', '1' );
		wpsc_update_customer_meta( 'wpec_keys_renewal_key', $license['license_key'] );

		return true;

	}

	return new WP_Error( 'renewal_error', __( 'Something went wrong while attempting to apply the renewal', 'edd_sl' ) );

}

function wpec_lic_set_renewal_flag( $data ) {

	if( ! wpec_lic_renewals_enabled() ) {
		return;
	}

	$renewal      = wpsc_get_customer_meta( 'wpec_keys_is_renewal' );
	$renewal_key  = wpsc_get_customer_meta( 'wpec_keys_renewal_key' );

	if( ! empty( $renewal ) && ! empty( $renewal_key ) ) {

		$purchase_log = new WPSC_Purchase_Log( $data['purchase_log_id'] );
		$purchase_log->set( '_wpec_lic_is_renewal', '1' )->save();
		$purchase_log->set( '_wpec_lic_renewal_key', $renewal_key )->save();

		wpsc_delete_customer_meta( 'wpec_keys_renewal_key' );
		wpsc_delete_customer_meta( 'wpec_keys_license_key' );
		wpsc_delete_customer_meta( 'wpec_keys_is_renewal' );
	}
}
add_action( 'wpsc_submit_checkout', 'wpec_lic_set_renewal_flag' );

//Add the JS for checkout page
function wpec_keys_checkout_js() {
?>
	<script>
	jQuery(document).ready(function($) {
		$('#wpec_keys_show_renewal_form, #wpec-cancel-license-renewal').click(function(e) {
			e.preventDefault();
			$('#wpec-license-key-container-wrap,#wpec_keys_show_renewal_form,.wpec-sl-renewal-actions').toggle();
			$('#wpec_keys_license_key').focus();
		});

		$('#wpec_keys_license_key').keyup(function(e) {
			var input  = $('#wpec_keys_license_key');
			var button = $('#wpec-add-license-renewal');

			if ( input.val() != '' ) {
				button.prop("disabled", false);
			} else {
				button.prop("disabled", true);
			}
		});
	});
	</script>
<?php
}
add_action( 'wp_head', 'wpec_keys_checkout_js' );
