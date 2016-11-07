<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPEC_Licensing_API
 */
class WPEC_Licensing_API {
	protected $updateServer;

	public function __construct() {
		
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/class-wpec-licensing-package.php' );
		// Get latest version
		add_action( 'wpec_lic_get_version', array( $this, 'get_remote_latest_version' ) );
		//Activate License
		add_action( 'wpec_lic_activate_license', array( $this, 'remote_license_activation' ) );
		//Deactivate License
		add_action( 'wpec_lic_deactivate_license', array( $this, 'remote_license_deactivation' ) );
		// Check license remotely
		add_action( 'wpec_lic_check_license', array( $this, 'remote_license_check' ) );
		
	}

	public function get_remote_latest_version( $data ) {
		
		if ( $data['wpec_lic_action'] != 'get_version' ) {
			return;
		}
		
		$url       = isset( $data['url'] )       ? sanitize_text_field( urldecode( $data['url'] ) )          : false;
		$license   = isset( $data['license'] )   ? sanitize_text_field( urldecode( $data['license'] ) )      : false;
		$slug      = isset( $data['slug'] )      ? sanitize_text_field( urldecode( $data['slug'] ) )         : false;
		$item_id   = isset( $data['item_id'] )   ? absint( $data['item_id'] )                                : false;

		$response  = array(
			'new_version'   => '',
			'sections'      => '',
			'license_check' => '',
			'msg'           => ''
		);
		// set content type of response
		header( 'Content-Type: application/json' );
		if( empty( $item_id ) ) {
			$response['msg'] = __( 'No item provided', 'wpec-licensing' );
			echo json_encode( $response ); exit;
		}
		$download = get_post( $item_id );
		if( ! $download ) {
			$response['msg'] = __( 'Invalid item ID or name provided', 'wpec-licensing' );
			echo json_encode( $response ); exit;
		}
		$slug        = ! empty( $slug ) ? $slug : $download->post_name;
		$description = ! empty( $download->post_excerpt ) ? $download->post_excerpt : $download->post_content;
		
		$package_download = new WPEC_Licensing_Package_Download;
		$package = $package_download->get_encoded_download_package_url( $item_id, $license, $url );
		
		$file_id = get_post_meta( $item_id, '_wpec_lic_upgrade_file', true );
		$file_data = get_post( $file_id );
		
		if ( $file_data ) {
			$file_name = basename( $file_data->post_title );
			$file_path = WPSC_FILE_DIR . $file_name;			
		}

		$meta_info = new WPEC_Licensing_Package_Info();
		$readme_meta = $meta_info->fromArchive( $file_path, $item_id, $slug );
		
		$response = array(
			'new_version'   => $readme_meta['version'],
			'name'          => $readme_meta['name'],
			'slug'          => $slug,
			'url'           => esc_url( add_query_arg( 'changelog', '1', get_permalink( $item_id ) ) ),
			'last_updated'  => human_time_diff( strtotime( $download->post_modified_gmt ), current_time( 'timestamp', 1)).' ago',
			'homepage'      => $readme_meta['homepage'],
			'package'       => $package,
			'download_link' => $package,
			'sections'      => serialize(
				array(
					'description' => wpautop( strip_tags( $description, '<p><li><ul><ol><strong><a><em><span><br>' ) ),
					'changelog'   => $readme_meta['sections']['changelog'],
				)
			),
		);
		
		if ( ! empty( $readme_meta['tested'] ) ) {
			$response['tested'] = $readme_meta['tested'];
		}		
		
		echo json_encode( $response );
		exit;
	}
	
	public function remote_license_activation( $data ) {

		if ( $data['wpec_lic_action'] != 'activate_license' ) {
			return;
		}
		
		$license     = ! empty( $data['license'] ) ? urldecode( $data['license'] ) : false;
		$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';

		if( empty( $url ) ) {
			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );
		}
		
		$args = array(
			'key'       => $license,
			'url'       => $url,
		);
		
		$result = wpec_product_licensing()->check_license( $args );
		$success = true;
		switch( $result ) {
			case 'expired':
				$success = false;
				$license_check = 'invalid';
				$message = __( 'Your license has expired, please renew it.', 'wpec-licensing' );
				break;
			case 'disabled':
				$success = false;
				$license_check = 'invalid';
				$message = __( 'Your license has been disabled.', 'wpec-licensing' );
				break;
			case 'already_active':
				$success = false;
				$license_check = 'invalid';
				$message = __( 'License is already activated for this domain.', 'wpec-licensing' );
				break;
			case 'limit_reach':
				$success = false;
				$license_check = 'invalid';
				$message = __( 'License activation limit reached.', 'wpec-licensing' );
				break;
			case 'valid':
				$license_check = 'valid';
				$message = __( 'License Activated.', 'wpec-licensing' );
				break;
			default:
				$success = false;
				$license_check = 'invalid';
				$message = __( 'Your license could not be validated.', 'wpec-licensing' );
				break;
		}
	
		//License validated. Go and activate the site
		if ( $success ) {
			$this->insert_site( $license, $url );
		}
		$license_data = $this->get_license_details( $license );
			
		$result = array(
				'success'        => (bool) $success,
				'message'		 => $message,
				'license'        => $license_check,
				'license_key'    => $license,
				'item_name'      => $license_data['product_name'],
				'item_id'        => wp_get_post_parent_id( $license_data['product_id'] ),
				'purchase_id'    => $license_data['purchase_id'],
				'expiration'     => $license_data['expiration_date'],
				'customer_email' => $license_data['email'],
		);
		header( 'Content-Type: application/json' );
		echo json_encode( $result );
		exit;		
	}
	
	public function remote_license_deactivation( $data ) {
	
		if ( $data['wpec_lic_action'] != 'deactivate_license' ) {
			return;
		}
		
		$license     = ! empty( $data['license'] ) ? urldecode( $data['license'] ) : false;
		$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';

		if( empty( $url ) ) {
			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );
		}
		
		$args = array(
			'expiration' => time(),
			'key'       => $license,
			'url'       => $url,
		);
		
		$license_data = $this->get_license_details( $license );
		$success = true;
		
		$is_lifetime_license = $license_data['expiration_date'] != 'lifetime' ? false : true;
		if ( ! $is_lifetime_license && $args['expiration'] > strtotime( $license_data['expiration_date'] ) ) {
			$success = false;
			$license_check = 'failed';
			$message = __( 'Your license has expired, please renew it.', 'wpec-licensing' );
		}
		
		if( $success ) {
			$license_check = 'deactivated';
			$message = __( 'Your license has been removed from this URL.', 'wpec-licensing' );
			$this->delete_site( $license, $url );
		}
		
		$result = array(
				'success'        => (bool) $success,
				'message'		 => $message,
				'license'        => $license_check,
				'license_key'    => $license,
				'item_name'      => $license_data['product_name'],
				'item_id'        => wp_get_post_parent_id( $license_data['product_id'] ),
				'purchase_id'    => $license_data['purchase_id'],
				'expiration'     => $license_data['expiration_date'],
				'customer_email' => $license_data['email'],
		);
		header( 'Content-Type: application/json' );
		echo json_encode( $result );
		exit;		
	}	
	
	public function remote_license_check( $data ) {
		
		if ( $data['wpec_lic_action'] != 'check_license' ) {
			return;
		}
		
		$item_id     = ! empty( $data['item_id'] )   ? absint( $data['item_id'] ) : false;
		$license     = urldecode( $data['license'] );
		$url         = isset( $data['url'] ) ? urldecode( $data['url'] ) : '';

		if( empty( $url ) ) {
			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );
		}		

		$args = array(
			'item_id'   => $item_id,
			'key'       => $license,
			'url'       => $url,
		);
		$result = $this->check_license( $args );
		$license_data = $this->get_license_details( $license );
		$result = array(
				'success'        => (bool) $result,
				'message'		 => '',
				'license'        => $result,
				'license_key'    => $license,
				'item_name'      => $license_data['product_name'],
				'item_id'        => wp_get_post_parent_id( $license_data['product_id'] ),
				'purchase_id'    => $license_data['purchase_id'],
				'expiration'     => $license_data['expiration_date'],
				'customer_email' => $license_data['email'],
		);
		
		header( 'Content-Type: application/json' );
		echo json_encode( $result );
		exit;
	}
	
	public function insert_site( $license, $site_url = '' ) {
		global $wpdb;
		
		if( empty( $license ) ) {
			return false;
		}
		if( empty( $site_url ) ) {
			return false;
		}

		$license_data = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `license_key` = '".$license."' LIMIT 1", ARRAY_A );
		
		if ( ! $license_data['id'] ) {
			return;
		}
		
		$sites = unserialize( $license_data['active_url'] );
		if ( false === $sites ) {
			$sites = array();
		}

		if( in_array( $site_url, $sites ) ) {
			return false; // Site already tracked
		}
		$sites[] = $site_url;
		
		$wpdb->query( "UPDATE `{$wpdb->prefix}wpec_licensing_licenses` SET url_count = url_count+1, active_url = '".serialize( $sites )."' WHERE `license_key` = '". $license ."' LIMIT 1;" );
	}

	public function delete_site( $license, $site_url = '' ) {
		global $wpdb;
		
		if( empty( $license ) ) {
			return false;
		}
		if( empty( $site_url ) ) {
			return false;
		}

		$license_data = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `license_key` = '".$license."' LIMIT 1", ARRAY_A );
		
		if ( ! $license_data['id'] ) {
			return;
		}
		
		$sites = unserialize( $license_data['active_url'] );
		if ( false === $sites ) {
			$sites = array();
		}

		if( ! in_array( $site_url, $sites ) ) {
			return false; // Site already tracked
		}

		$key = array_search( $site_url, $sites );
		unset( $sites[ $key ] );
		
		$wpdb->query( "UPDATE `{$wpdb->prefix}wpec_licensing_licenses` SET url_count = url_count-1, active_url = '".serialize( $sites )."' WHERE `license_key` = '". $license ."' LIMIT 1;" );
	}
	
	public function get_license_details( $license ) {
		global $wpdb;
		
		if( empty( $license ) ) {
			return false;
		}
		
		$license_data = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `license_key` = '".$license."' LIMIT 1", ARRAY_A );
		
		if ( ! $license_data['id'] ) {
			return;
		}

		return $license_data;
	}

	public function check_license( $args ) {
		global $wpdb;
		
		$defaults = array(
			'key'        => '',
			'item_name'  => '',
			'item_id'    => 0,
			'expiration' => time(), // right now
			'url'        => ''
		);
		$args = wp_parse_args( $args, $defaults );
		
		if ( empty( $args['key'] ) ) {
			return 'invalid';
		}
		
		$license = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `license_key` = '".$args['key']."' LIMIT 1", ARRAY_A );
		
		if ( ! $license['id'] ) {
			return 'invalid';
		}
		
		// grab info about the license
		$license_expires    = strtotime( $license['expiration_date'] );
		$license_key        = $license['license_key'];
		$license_status     = $license['active'];
		$license_prod_id	= $license['product_id'];
		$item_name          = html_entity_decode( $license['product_name'] );
		$url                = ! empty( $args['url'] ) ? $args['url'] : '';

		if ( $args['key'] != $license_key ) {
			return 'invalid'; // keys don't match
		}
		$is_lifetime_license = $license['expiration_date'] != 'lifetime' ? false : true;
		if ( ! $is_lifetime_license && $args['expiration'] > $license_expires ) {
			return 'expired'; // this license has expired
		}
		if ( '1' != $license_status ) {
			return 'disabled'; // this license is not active.
		}
		return 'valid'; // license still active
	}
	
}

new WPEC_Licensing_API();