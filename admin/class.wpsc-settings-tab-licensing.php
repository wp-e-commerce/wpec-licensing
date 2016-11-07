<?php
final class WPSC_Settings_Tab_Wpec_Licensing extends WPSC_Settings_Tab {
 	
	public function display () {
		
		// Get saved options
		$settings = get_option( 'wpec_licensing' );

		?>

		<!-- Cheeky font-awesome injection -->
		<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">

		<h3><?php _e( 'Product Licensing Settings', 'wpec-licensing' ); ?></h3>
		<table class="wpsc_options form-table">
			<tbody>
			  <tr>
				<th scope="row">
				  <label for="wpsc_options[wpec_licensing][enable_renewals]" />
					<?php _e( 'Enable license renewals', 'wpec-licensing' ); ?>
				  </label>
				</th>
				<td valign="top">
				  <input type="checkbox" name="wpsc_options[wpec_licensing][enable_renewals]" value="yes"<?php echo ( isset( $settings['enable_renewals'] ) && 'yes' == $settings['enable_renewals'] ) ? ' checked="checked"' : NULL; ?>/>&nbsp; <?php _e( 'Enable customers to renew their licenses', 'wpsc-xero' ); ?>
				</td>
			  </tr>

			  <tr>
				<th scope="row">
				  <label for="wpsc_options[wpec_licensing][renewal_discount]" />
					<?php _e( 'Renewal discount', 'wpec-licensing' ); ?>
				  </label>
				</th>
				<td valign="top">
				  <input type="text" name="wpsc_options[wpec_licensing][renewal_discount]" value="<?php echo $settings['renewal_discount']; ?>" />
				  <p class="description">
					<?php _e( 'Enter a discount amount as a percentage, such as 30. Or enter 0 for no discount.', 'wpec-licensing' ); ?>
				  </p>
				</td>
			  </tr>
			</tbody>
		</table>
	<?php
	}
}
