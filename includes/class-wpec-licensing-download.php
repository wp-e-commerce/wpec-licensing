<?php
/**
 * The class to process downloading a package URL from the tokenized URLs
 *
 * @since  3.2.4
 */
class WPEC_Licensing_Package_Download {


	/**
	 * Initialize the request
	 */
	public function __construct() {
		// Download files
		add_action( 'wpec_lic_package_download', array( $this, 'process_package_download' ) );
	}

	/**
	 * Process the request for a package download
	 *
	 * @since  3.2.4
	 * @return  void
	 */
	public function process_request() {

		$data = $this->parse_url();

		if( ! empty( $data ) && is_array( $data ) ) {

			foreach ( $data as $key => $arg ) {
				$_GET[ $key ] = $arg;
			}
			
			do_action( 'wpec_lic_package_download' );

			// We're firing a download URL, just get out
			wp_die();

		}
	}

	/**
	 * Parse the URL for the package downloader
	 *
	 * @since  3.2.4
	 * @return array Array of parsed url information
	 */
	private function parse_url() {

		if( false === stristr( $_SERVER['REQUEST_URI'], 'wpec-lic/package_download' ) ) {
			return false; // Not a package download request
		}

		$data      = array();
		$url_parts = parse_url( $_SERVER['REQUEST_URI'] );
		$paths     = array_values( explode( '/', $url_parts['path'] ) );

		$token  = end( $paths );
		$values = explode( ':', base64_decode( $token ) );

		if ( count( $values ) !== 5 ) {
			wp_die( __( 'Invalid token supplied', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
		}
		
		$expires     = $values[0];
		$license_key = $values[1];
		$download_id = (int) $values[2];
		$url         = str_replace( '@', ':', $values[4] );

		$license_check_args = array(
			'url'        => $url,
			'key'        => $license_key,
			'item_id'    => $download_id,
		);
		$license_status = $this->check_license( $license_check_args );

		switch( $license_status ) {
			case 'expired':
				//$renewal_link = add_query_arg( 'wpe_license_key', $license_key, edd_get_checkout_uri() );
				wp_die( sprintf( __( 'Your license has expired, please <a href="%s" title="Renew your license">renew it</a> to install this update.', 'wpec-licensing' ), $renewal_link ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
				break;
			case 'inactive':
			case 'site_inactive':
				wp_die( __( 'Your license has not been activated for this domain, please activate it first.', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
				break;
			case 'disabled':
				wp_die( __( 'Your license has been disabled.', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
				break;
			case 'valid':
				break;
			default:
				wp_die( __( 'Your license could not be validated.', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
				break;
		}

		$download_name  = get_the_title( $download_id );
		$file_key       = get_post_meta( $download_id, '_wpec_lic_upgrade_file', true );

		$hash = md5( $download_name . $file_key . $download_id . $license_key . $expires );
		if ( ! hash_equals( $hash, $values[3] ) ) {
			wp_die( __( 'Provided hash does not validate.', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
		}

		$data = array(
			'expires'       => $expires,
			'license'       => $license_key,
			'id'            => $download_id,
			'key'           => $hash,
		);

		return $data;

	}

	public function get_encoded_download_package_url( $item_id = 0, $license_key = '', $url = '' ) {

		$package_url = '';

		if( ! empty( $license_key ) ) {

			$download_name = get_the_title( $item_id );
			$expires       = strtotime( '+24 hours' );
			$file_key      = get_post_meta( $item_id, '_wpec_lic_upgrade_file', true );
			$hash          = md5( $download_name . $file_key . $item_id . $license_key . $expires );
			$url           = str_replace( ':', '@', $url );

			$token = base64_encode( sprintf( '%s:%s:%d:%s:%s', $expires, $license_key, $item_id, $hash, $url ) );

			$package_url = trailingslashit( home_url() ) . 'wpec-lic/package_download/' . $token;

		}

		return $package_url;

	}

	/**
	 * Deliver the file download
	 *
	 * @since  3.2.4
	 * @return void
	 */
	public function process_package_download() {
		
		if ( isset( $_GET['key'] ) && isset( $_GET['id'] ) && isset( $_GET['license'] ) && isset( $_GET['expires'] ) ) {

			$id      = absint( urldecode( $_GET['id'] ) );
			$hash    = urldecode( $_GET['key'] );
			$license = sanitize_text_field( urldecode( $_GET['license'] ) );
			$expires = is_numeric( $_GET['expires'] ) ? $_GET['expires'] : urldecode( base64_decode( $_GET['expires'] ) );

			$requested_file = $this->get_download_package( $id, $license, $hash, $expires );
			
			$file_key  = get_post_meta( $id, '_wpec_lic_upgrade_file', true );
			$file = get_post( $file_key );
			$file_url = WPSC_FILE_URL . $file->post_title;
			$file_path = WPSC_FILE_DIR . $file->post_title;
			
			if ( is_file( $file_path ) ) {
		
				if( !ini_get('safe_mode') ) set_time_limit(0);
				header( 'Content-Type: ' . $file->post_mime_type );
				header( 'Content-Length: ' . filesize( $file_path ) );
				header( 'Content-Transfer-Encoding: binary' );
				header( 'Content-Disposition: attachment; filename="' . stripslashes( $file->post_title ) . '"' );
				if ( isset( $_SERVER["HTTPS"] ) && ($_SERVER["HTTPS"] != '') ) {
					/*
					  There is a bug in how IE handles downloads from servers using HTTPS, this is part of the fix, you may also need:
					  session_cache_limiter('public');
					  session_cache_expire(30);
					  At the start of your index.php file or before the session is started
					 */
					header( "Pragma: public" );
					header( "Expires: 0" );
					header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
					header( "Cache-Control: public" );
				} else {
					header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
				}
				header( "Pragma: public" );
				header( "Expires: 0" );
				// destroy the session to allow the file to be downloaded on some buggy browsers and webservers
				session_destroy();
				wpsc_readfile_chunked( $file_path );
				exit();
			}else{
				wp_die(__('Sorry, something has gone wrong with your download!', 'wp-e-commerce'));
			}
			
		} else {
			wp_die( __( 'You do not have permission to download this file', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
		}
		exit;
	}

	/**
	 * Deliver the package download URL
	 */
	public function get_download_package( $download_id = 0, $license_key = '', $hash, $expires = 0 ) {
		$file_key  = get_post_meta( $download_id, '_wpec_lic_upgrade_file', true );
		$file = get_post( $file_key );
		
		$file_url = WPSC_FILE_URL . $file->post_title;

		$download_name = get_the_title( $download_id );

		if ( ! empty( $hash ) && ! hash_equals( md5( $download_name . $file_key . $download_id . $license_key . $expires ), $hash ) ) {
			wp_die( __( 'You do not have permission to download this file. An invalid hash was provided.', 'wpec-licensing' ), __( 'Error', 'wpec-licensing' ), array( 'response' => 401 ) );
		}
		
		return $file_url;
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
		if( empty( $args['item_id'] ) ) {
			return 'invalid_item_id';
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