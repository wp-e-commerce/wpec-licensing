<?php
/**
 * The Account > API Keys template.
 *
 * Displays the users api keys.
 *
 * @package WPSC
 */
 global $col_count;
 
 if ( _wpsc_has_license_keys() ) : ?>

	<table class="logdisplay">
	
		<!--
		<tr class="toprow">
			<th class="status"><?php _e( 'Status', 'wpsc' ); ?></th>
			<th class="date"><?php _e( 'Date', 'wpsc' ); ?></th>
			<th class="price"><?php _e( 'Order ID', 'wpsc' ); ?></th>
		</tr>
		-->

		<?php wpsc_user_license_keys(); ?>

	</table>	
	

<?php else : ?>

	<table>
		<tr>
			<td><?php _e( 'You have no Licenses yet.', 'wpsc' ); ?></td>
		</tr>
	</table>

<?php endif; ?>