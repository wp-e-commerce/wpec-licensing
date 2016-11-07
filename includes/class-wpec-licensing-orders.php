<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPEC_Licensing_Orders
 */
class WPEC_Licensing_Orders {

	/**
	 * Constructor
	 */
	public function __construct() {
		//Generate license on purchase log status change
		add_action( 'wpsc_update_purchase_log_status', array( $this, 'purchase_log_license_status_change' ), 0, 4 );
		//Send license info in the customer notification email.
		add_filter( 'wpsc_purchase_log_customer_notification_raw_message', array( $this, 'customer_email' ), 10, 2 );
		//Deleting a purchase
		add_action( 'wpsc_purchase_log_delete', array( $this, 'delete_order' ), 99, 1 );
		//Renew license on purchase log status change
		//add_action( 'wpsc_update_purchase_log_status', array( $this, 'renew_license' ), 0, 4 );		
	}
	
	public function purchase_log_license_status_change( $log_id, $current_status, $previous_status, $log ) {
		$purchase_log = new WPSC_Purchase_Log( $log_id );
		$is_renewal = $purchase_log->get( '_wpec_lic_is_renewal' );
		$license_key = $purchase_log->get( '_wpec_lic_renewal_key' );
		$license_data = wpec_lic_get_license_by_key( $license_key );
		
		$renew_product = $license_data['product_id'];
		
		foreach ( $log->get_cart_contents() as $item ) {
			if( $license_data && $item->prodid == $renew_product && $is_renewal ) {
				$this->renew_license( $log_id, $log, $license_data );
			} else {
				$this->generate_license( $item, $log_id, $log );
			}
		}
	}

	public function renew_license( $log_id, $log, $license_data) {
		
		if( ! wpec_lic_renewals_enabled() ) {
			return;
		}
		
		$purchase_log = new WPSC_Purchase_Log( $log_id );
		$is_renewal = $purchase_log->get( '_wpec_lic_is_renewal' );

		// Bail if this is not a renewal item
		if( empty( $is_renewal ) ) {
			return;
		}

		$license_id = $license_data['id'];

		if ( $log->is_transaction_completed() && $license_id ) {
			wpec_license_renew_license( $license_id );
		}
	}
	
	public function generate_license( $item, $log_id, $log ) {
		global $wpdb;

		$generate_license = get_post_meta( $item->prodid, '_wpec_lic_enabled', true );
		$activation_limit = get_post_meta( $item->prodid, '_wpec_lic_limit', true );
		$purchase_id = $log_id;

		if ( ! get_post_meta( $item->prodid, '_wpec_lic_enabled', true ) ) {
			return;
		}
		
		if ( $log->is_transaction_completed() ) {
			//Purchase marked as complete. Create license.
			$form_data =  new WPSC_Checkout_Form_Data( $purchase_id ) ;
			$customer_email = $form_data->get( 'billingemail' );

			if ( $generate_license == 1 ) {
				$key_results_sql = "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `purchase_id` = '".$purchase_id."' AND `cart_item_id` = '".$item->id."' AND `product_id` = '".$item->prodid."' ";
				$key_results = $wpdb->get_results( $key_results_sql, ARRAY_A );
				
				if( count( $key_results ) < 1 ) {
					for ( $i = 0; $i < absint( $item->quantity ); $i ++ ) {
						// Generate a licence key
						$data = array(
							'license_key'	   => $this->generate_license_key(),
							'purchase_id'         => $purchase_id,
							'email' 		   => $customer_email,
							'user_id'          => $log->get( 'user_ID' ),
							'product_id'       => $item->prodid,
							'cart_item_id'		   => $item->id,
							'product_name'	   => get_the_title( $item->prodid ),
							'active_limit' => $activation_limit,
							'expiration_date'     => wpec_lic_calc_license_expiration($item->prodid,  date("Y-m-d H:i:s") ) == 'lifetime' ? 'lifetime' : wpec_lic_calc_license_expiration($item->prodid,  date("Y-m-d H:i:s") ),
							'active'	   => '1'
						);
						
						$licence_id = $this->save_licence_key( $data );
					}
				} elseif ( count( $key_results ) >= 1 ) {
					$wpdb->query("UPDATE `{$wpdb->prefix}wpec_licensing_licenses` SET active = '1' WHERE `purchase_id` = '".$purchase_id."' AND `cart_item_id` = '".$item->id."' AND `product_id` = '".$item->prodid."' ");
				}
			}
		} else {
			//Disable license if status is other than completed
			$key_results_sql = "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `purchase_id` = '".$purchase_id."' AND `cart_item_id` = '".$item->id."' AND `product_id` = '".$item->prodid."' ";
			$key_results = $wpdb->get_results( $key_results_sql, ARRAY_A );
			
			if ( $key_results ) {
				$wpdb->query("UPDATE `{$wpdb->prefix}wpec_licensing_licenses` SET active = '0' WHERE `purchase_id` = '".$purchase_id."' AND `cart_item_id` = '".$item->id."' AND `product_id` = '".$item->prodid."' ");
			}
		}

	}

	public function customer_email( $message, $notification ) {
	  global $wpdb;

		$log        = $notification->get_purchase_log();
		$cart_items = $log->get_cart_contents();
		$item_message_section = '';

		foreach ( $cart_items as $cart_item ) {
			if ( ! get_post_meta( $cart_item->prodid, '_wpec_lic_enabled', true ) )
				continue;

			$purchase_id = $cart_item->purchaseid;

			$licence_keys = $wpdb->get_results( $wpdb->prepare( "
				SELECT * FROM {$wpdb->prefix}wpec_licensing_licenses
				WHERE purchase_id = %d
			", $purchase_id ) );

			$item_message_section .= "\n\n";
			$item_message_section .= "<strong>Product Licenses: </strong>" . "\n";

			foreach( (array)$licence_keys as $key_result ) {
				$item_message_section .= "   Product Name: ".$key_result->product_name."\n";
				$item_message_section .= "   License Key : ".$key_result->license_key."\n\n";
			}
		}

		if ( $log->is_transaction_completed() ) {
			return $message . $item_message_section;
		} else {
			return $message;
		}
	}
	
	public function delete_order ( $log_id ) {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( $log_id > 0 ) {
			$wpdb->delete( "{$wpdb->prefix}wpec_licensing_licenses", array( 'purchase_id' => $log_id ) );
		}
	}
	
	public function save_licence_key( $data ) {
		global $wpdb;
		
		$defaults = array(
			'email'				=> '',
			'license_key' 		=> self::generate_license_key(),
			'first_name'     	=> '',
			'last_name'         => '',
			'company'		    =>'',
			'active_url'        => '',
			'url_count'       	=> '',
			'active_limit'    	=> '',
			'active' 			=> '1',
			'purchase_id'		=> '',
			'cart_item_id'      => '',
			'product_name'      => '',
			'product_id'	    => '',			
			'user_id'	        => '',
			'purchase_date'	   	=> date("Y-m-d H:i:s"),
			'expiration_date'	=> ''
		);
		
		$insert = wp_parse_args( $data, $defaults );
		$wpdb->insert( $wpdb->prefix . 'wpec_licensing_licenses', $insert );
		
		return $wpdb->insert_id;
	}
	
	/**
	 * generates a unique id that is used as the license code
	 *
	 * @since 1.0
	 * @return string the unique ID
	 */
	public static function generate_license_key() {
		$key = md5( sha1( uniqid( rand(), true ) ) );
		return apply_filters( 'wpec_generate_license_key', $key );
	}
}

new WPEC_Licensing_Orders();