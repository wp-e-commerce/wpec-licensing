<?php
/*
Plugin Name: WP eCommerce Product Licensing
Upgrade URI: http://www.wpecommerce.org
Description: A module that allows License creation for products
Version: 1.0
Author: Mihai
Author URI: http://wpecommerce.org
*/

class WPEC_Licensing {
	
	private static $instance;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Define constants
		define( 'WPEC_LICENSING_VERSION', '1.0.0' );
		define( 'WPEC_LICENSING_PLUGIN_FILE', __FILE__ );
		define( 'WPEC_LICENSING_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'WPEC_LICENSING_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Includes
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/class-api.php' );
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/scripts.php' );
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/class-wpec-licensing-download.php' );
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/class-wpec-licensing-orders.php' );
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/account_page.php' );
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/functions.php' );
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/metaboxes.php' );
		
		//License renewal stuff
		include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/renewals.php' );
		
		//License activation/validation
		//include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/license_activation.php' );

		if ( is_admin() ) {
			include_once( WPEC_LICENSING_PLUGIN_DIR . '/admin/admin.php' );
			include_once( WPEC_LICENSING_PLUGIN_DIR . '/admin/class-wpec_license-manage-table.php' );
			include_once( WPEC_LICENSING_PLUGIN_DIR . '/includes/admin-ajax.php' );
		}

		// Hooks
		//add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		$this->actions();
	}
	
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPEC_Licensing ) ) {
			self::$instance = new WPEC_Licensing;
		}
		return self::$instance;
	}

	public function actions() {
		//error_log( print_R($_GET,TRUE) );
		add_action( 'init', array( $this, 'wpec_lic_get_actions' ) );
		add_action( 'init', array( $this, 'wpec_lic_post_actions' ) );
		add_action( 'init', array( $this, 'load_api_endpoint' ) );
		add_action( 'after_setup_theme', array( $this, 'reduce_query_load' ) );
	}

	function check_license( $args ) {
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

		$sites = unserialize( $license['active_url'] );
		if ( false === $sites ) {
			$sites = array();
		}
		
		if( empty( $url ) ) {
			// Attempt to grab the URL from the user agent if no URL is specified
			$domain = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
			$url    = trim( $domain[1] );
		}
		if ( $args['key'] != $license_key ) {
			return 'invalid'; // keys don't match
		}
		if( ! empty( $args['item_id'] ) ) {
			if( $license_prod_id != $args['item_id'] ) {
				return 'invalid_item_id';
			}
		}
		$is_lifetime_license = $license['expiration_date'] != 'lifetime' ? false : true;
		if ( ! $is_lifetime_license && $args['expiration'] > $license_expires ) {
			return 'expired'; // this license has expired
		}
		if ( '1' != $license_status ) {
			return 'disabled'; // this license is not active.
		}
		//Check if not already activated
		if ( in_array( $url, $sites ) ) {
			return 'already_active'; // this license is already active on this domain
		}
		//Check activation limit
		if ( $license['active_limit'] != '0' && $license['active_limit'] == $license['url_count'] ) {
			return 'limit_reach'; // Limit activation reached
		}

		return 'valid'; // license still active
	}
	
	/**
	 * @return void
	 */
	public function load_api_endpoint() {
		// if this is an API Request, load the Endpoint
		if ( ! is_admin() && $this->is_api_request() !== false ) {
			$request_type  = $this->get_api_endpoint();
			if ( ! empty( $request_type ) ) {
				$request_class = str_replace( '_', ' ', $request_type );
				$request_class = 'WPEC_Licensing_' . ucwords( $request_class );
				$request_class = str_replace( ' ', '_', $request_class );
				if ( class_exists( $request_class ) ) {
					$api_request = new $request_class;
					$api_request->process_request();
				}
			}
		}
	}
	
	private function is_api_request() {
		$trigger = false;
		$allowed_endpoints = array(
			'package_download',
		);
		foreach ( $allowed_endpoints as $endpoint ) {
			$trigger = $this->is_endpoint_active( $endpoint );
			if ( $trigger ) {
				$trigger = true;
				break;
			}
		}
		return (bool)$trigger;
	}
	
	private function is_endpoint_active( $endpoint = '' ) {
		$is_active = stristr( $_SERVER['REQUEST_URI'], 'wpec-lic/' . $endpoint ) !== false;
		if ( $is_active ) {
			$is_active = true;
		}
		return (bool) $is_active;
	}
	
	private function get_api_endpoint() {
		$url_parts = parse_url( $_SERVER['REQUEST_URI'] );
		$paths     = explode( '/', $url_parts['path'] );
		$endpoint  = '';
		foreach ( $paths as $index => $path ) {
			if ( 'wpec-lic' === $path ) {
				$endpoint = $paths[ $index + 1 ];
				break;
			}
		}
		return $endpoint;
	}
	
	public function wpec_lic_get_actions() {
		if ( isset( $_GET['wpec_lic_action'] ) ) {
			do_action( 'wpec_lic_' . $_GET['wpec_lic_action'], $_GET );
		}
	}

	public function wpec_lic_post_actions() {
		if ( isset( $_POST['wpec_lic_action'] ) ) {
			do_action( 'wpec_lic_' . $_POST['wpec_lic_action'], $_POST );
		}
	}

	/**
	 * Removes the queries caused by `widgets_init` for remote API calls (and for generating the download)
	 *
	 * @return void
	 */
	public function reduce_query_load() {
		if( ! isset( $_REQUEST['wpec_lic_action'] ) ) {
			return;
		}
		$actions = array(
			'activate_license',
			'deactivate_license',
			'get_version',
			'package_download',
			'check_license'
		);
		if( in_array( $_REQUEST['wpec_lic_action'], $actions ) ) {
			remove_all_actions( 'widgets_init' );
		}
	}
	
}

function wpec_product_licensing_activation() {
	global $wpdb;
	
	$table_version = '1.0';

	$wpdb->hide_errors();

	$collate = '';

	if ( $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty($wpdb->charset ) ) {
			$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty($wpdb->collate ) ) {
			$collate .= " COLLATE $wpdb->collate";
		}
	}

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	$sql = "CREATE TABLE ". $wpdb->prefix . "wpec_licensing_licenses (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(255) NOT NULL,
		license_key varchar(255) NOT NULL,
		first_name varchar(255) NOT NULL,
		last_name varchar(255) NOT NULL,
		company varchar(255) NOT NULL,
		active_url varchar(255) NOT NULL,
		url_count bigint(10) NOT NULL,
		active_limit varchar(30) NOT NULL,
		active tinyint(1) NOT NULL,
		purchase_id bigint(20) unsigned NOT NULL default '0',
		cart_item_id bigint(20) unsigned NOT NULL default '0',
		product_name varchar(255) NOT NULL,
		product_id bigint(20) NOT NULL,
		user_id bigint(20) NULL,
		purchase_date datetime NULL,
		expiration_date varchar(50) NOT NULL,
		PRIMARY KEY  (id),
		KEY id (id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	dbDelta( $sql );
}

// Activation hooks
register_activation_hook( __FILE__, 'wpec_product_licensing_activation' );



function wpec_product_licensing() {
	return WPEC_Licensing::instance();
}
// Get WPeCommerce PRoduct Licensing running
add_action( 'plugins_loaded', 'wpec_product_licensing' )
?>