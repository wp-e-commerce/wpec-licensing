<?php
function wpec_licensing_new_meta_boxes() {
	$user_ID = get_current_user_id();

	if ( current_user_can( 'manage_options' ) ) {
		add_meta_box( 'wpec_licensing_add_license_to_product', 'Product License', 'wpec_licensing_product_meta_box', 'wpsc-product', 'normal', 'core' );
	}
}
add_action( 'admin_menu', 'wpec_licensing_new_meta_boxes' );

function wpec_licensing_product_meta_box() {
	global $post;

	echo '<input type="hidden" name="wpec_lic_meta_box_nonce" value="'. wp_create_nonce( basename( __FILE__ ) ). '" />';

	echo '<table class="form-table">';

		$enabled    = get_post_meta( $post->ID, '_wpec_lic_enabled', true ) ? true : false;
		$limit      = get_post_meta( $post->ID, '_wpec_lic_limit', true );
		$exp_unit   = get_post_meta( $post->ID, '_wpec_lic_exp_unit', true );
		$exp_length = get_post_meta( $post->ID, '_wpec_lic_exp_length', true );
		$file_id    = get_post_meta( $post->ID, '_wpec_lic_upgrade_file', true );
		$display    = $enabled ? '' : ' style="display:none;"';

		$is_limited = get_post_meta( $post->ID, '_wpec_lic_download_lifetime', true );
		$is_limited = empty( $is_limited );

		$display_length    = ( $enabled && $is_limited )  ? '' : ' style="display: none;"';

		echo '<tr>';
			echo '<td class="edd_field_type_text" colspan="2">';
				echo '<input type="checkbox" name="wpec_license_enabled" id="wpec_license_enabled" value="1" ' . checked( true, $enabled, false ) . '/>&nbsp;';
				echo '<label for="wpec_license_enabled">' . __( 'Check to enable license creation', 'wpec-licensing' ) . '</label>';
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display . ' class="wpec_lic_toggled_row">';
			echo '<td class="edd_field_type_text" colspan="2">';
				echo '<input type="number" class="small-text" name="wpec_lic_limit" id="wpec_lic_limit" value="' . esc_attr( $limit ) . '"/>&nbsp;';
				echo __( 'Limit number of times this license can be activated. Enter "0" for Unlimited.', 'wpec-licensing' );
			echo '</td>';
		echo '</tr>';

		echo '<tr' . $display . ' class="wpec_lic_toggled_row">';
			echo '<td class="edd_field_type_select">';
				echo '<p>' . __( 'How long are license keys valid for?', 'wpec-licensing' ) . '</p>';
				echo '<input ' . checked( false, $is_limited, false ) . ' type="radio" id="wpec_license_is_lifetime" name="wpec_lic_is_lifetime" value="1" /><label for="wpec_license_is_lifetime">' . __( 'Lifetime', 'wpec-licensing' ) . '</label>';
				echo '<br/ >';
				echo '<input ' . checked( true, $is_limited, false ) . ' type="radio" id="wpec_license_is_limited" name="wpec_lic_is_lifetime" value="0" /><label for="wpec_license_is_limited">' . __( 'Limited', 'wpec-licensing' ) . '</label>';
				echo '<p'  . $display_length . ' class="wpec_lic_toggled_row" id="wpec_license_length_wrapper">';
					echo '<input type="number" id="wpec_lic_exp_length" name="wpec_lic_exp_length" class="small-text" value="' . $exp_length . '"/>&nbsp;';
					echo '<select name="wpec_lic_exp_unit" id="wpec_lic_exp_unit">';
						echo '<option value="days"' . selected( 'days', $exp_unit, false ) . '>' . __( 'Days', 'wpec-licensing' ) . '</option>';
						echo '<option value="weeks"' . selected( 'weeks', $exp_unit, false ) . '>' . __( 'Weeks', 'wpec-licensing' ) . '</option>';
						echo '<option value="months"' . selected( 'months', $exp_unit, false ) . '>' . __( 'Months', 'wpec-licensing' ) . '</option>';
						echo '<option value="years"' . selected( 'years', $exp_unit, false ) . '>' . __( 'Years', 'wpec-licensing' ) . '</option>';
					echo '</select>';
				echo '</p>';
			echo '</td>';
		echo '</tr>';

		echo '<tr>';
			echo '<td class="edd_field_type_select" colspan="2">';
				echo '<select name="wpec_lic_upgrade_file" id="wpec_lic_upgrade_file">';
					$args = array(
						'post_type' => 'wpsc-product-file',
						'post_parent' => $post->ID,
						'numberposts' => -1,
						'post_status' => 'all'
					);
					$attached_files = (array) get_posts( $args );
					if ( ! empty( $attached_files ) ) {
						foreach( $attached_files as $file ) {
							echo '<option value="' . esc_attr( $file->ID ) . '" ' . selected( $file->ID, $file_id, false ) . '>' . esc_html( $file->post_title ) . '</option>';
						}
					} else {
						echo '<option value="">' . __( 'You must upload product files to select here', 'wpec-licensing' ) . '</option>';
					}
				echo '</select>&nbsp;';
				echo '<label for="wpec_lic_upgrade_file">' . __( 'Choose the source file to be used for automatic updates.', 'wpec-licensing' ) . '</label>';
			echo '</td>';
		echo '</tr>';
	echo '</table>';
}

/**
 * Save data from meta box
 *
 * @since 1.0
 */
function wpec_lic_product_meta_box_save( $post_id ) {

	global $post;

	// verify nonce
	if ( ! isset( $_POST['wpec_lic_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['wpec_lic_meta_box_nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	// Check for auto save / bulk edit
	if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX') && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
		return $post_id;
	}

	if ( isset( $_POST['post_type'] ) && 'wpsc-product' != $_POST['post_type'] ) {
		return $post_id;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	if ( isset( $_POST['wpec_license_enabled'] ) ) {
		update_post_meta( $post_id, '_wpec_lic_enabled', true );
	} else {
		delete_post_meta( $post_id, '_wpec_lic_enabled' );
	}

	if ( isset( $_POST['wpec_lic_limit'] ) ) {
		update_post_meta( $post_id, '_wpec_lic_limit', ( int ) $_POST['wpec_lic_limit'] );
	} else {
		delete_post_meta( $post_id, '_wpec_lic_limit' );
	}

	if ( isset( $_POST['wpec_lic_is_lifetime'] ) ) {
		$is_lifetime = $_POST['wpec_lic_is_lifetime'] === '1' ? 1 : 0;
		update_post_meta( $post_id, '_wpec_lic_download_lifetime', $is_lifetime );
	}

	if ( isset( $_POST['wpec_lic_exp_unit'] ) ) {
		update_post_meta( $post_id, '_wpec_lic_exp_unit', addslashes( $_POST['wpec_lic_exp_unit'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_wpec_lic_exp_unit' );
	}

	if ( isset( $_POST['wpec_lic_exp_length'] ) ) {
		update_post_meta( $post_id, '_wpec_lic_exp_length', addslashes( $_POST['wpec_lic_exp_length'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_wpec_lic_exp_length' );
	}

	if ( isset( $_POST['wpec_lic_upgrade_file'] ) ) {
		update_post_meta( $post_id, '_wpec_lic_upgrade_file', addslashes( $_POST['wpec_lic_upgrade_file'] ) ) ;
	} else {
		delete_post_meta( $post_id, '_wpec_lic_upgrade_file' );
	}

}
add_action( 'save_post', 'wpec_lic_product_meta_box_save' );
