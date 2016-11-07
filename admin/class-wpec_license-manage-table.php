<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPEC_Licenses_list_table extends WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'License', 'wpec-licensing' ), //singular name of the listed records
			'plural'   => __( 'Licenses', 'wpec-licensing' ), //plural name of the listed records
			'ajax'     => false //should this table support ajax?

		] );
	}
	
	/**
	 * Retrieve license's data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_licenses( $per_page = 10, $page_number = 1 ) {

		global $wpdb;
		
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : null;

		$sql = "SELECT * FROM {$wpdb->prefix}wpec_licensing_licenses ";

		if ( ! empty( $_GET['s'] ) ) {
			
			$search   = trim( $_GET['s'] );

			//Check if its an order id starting with #
			if( substr( $search , 0, 1 ) == '#'  ) {
				$purchaseid = substr( $search , 1 );
				$sql .= ' WHERE purchase_id = \'' . esc_sql( $purchaseid ) . '\'';
			} elseif( ! is_email( $search ) ) {			
				$sql .= ' WHERE license_key = \'' . esc_sql( $search ) . '\'';
			} else {
				$sql .= ' WHERE email = \'' . esc_sql( $search ) . '\'';
			}
		}
		
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		} else {
			
			$sql .= 'ORDER BY id DESC';
			
		}

		$sql .= " LIMIT $per_page";

		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

	
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}
	
	/**
	 * Delete a license record.
	 *
	 * @param int $id license ID
	 */
	public static function delete_license( $id ) {
		global $wpdb;

		$wpdb->delete(
			"{$wpdb->prefix}wpec_licensing_licenses",
			[ 'id' => $id ],
			[ '%d' ]
		);
	}
	
	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wpec_licensing_licenses";

		return $wpdb->get_var( $sql );
	}	
	
	/** Text displayed when no customer data is available */
	public function no_items() {
		_e( 'No licenses avaliable.', 'wpec-licensing' );
	}	
	
	 /**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_name( $item ) {

		// create a nonce
		$delete_nonce = wp_create_nonce( 'wpec_lic_delete_license' );

		$title = '<strong>' . $item['name'] . '</strong>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&license=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce )
		];

		return $title . $this->row_actions( $actions );
	}
	
	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item['id'] )
		);
	}
	
	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = array (
			'cb'     			=> '<input type="checkbox" />',
			'product_name'		=> __( 'Name', 'wpec-licensing' ),
			'license_key'		=> __( 'Key', 'wpec-licensing' ),
			'user_id'			=> __( 'User', 'wpec-licensing' ),
			'url_count'			=> __( 'Sites', 'wpec-licensing' ),
			'active_limit'		=> __( 'Active Limit', 'wpec-licensing' ),
			'expiration_date'	=> __( 'Expires', 'wpec-licensing' ),
			'purchase_date'		=> __( 'Purchased', 'wpec-licensing' )
		);
		return $columns;
	}

	/**
	 * Render a column when no column specific method exists.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {
			case 'user_id':
				$userlogin = 'n/a';
				if ( ! empty( $item['user_id'] ) ) {
					$user = get_userdata( $item['user_id'] );
					$userlogin = $user->user_login;
				}
				return $userlogin; 
				break;
				
			case 'purchase_date':
				
				return '<a href='. admin_url( 'index.php?page=wpsc-purchase-logs&c=item_details&id='.$item['purchase_id'] ) .'>'. $item['purchase_date'] .'</a>';
				break;

			case 'active_limit':
				
				$active_limit = $item['active_limit'];
				$active_limit = $active_limit > 0 ? esc_html( $active_limit ) : __( 'Unlimited', 'wpec-licensing' );
				
				echo '<span id="wpec-lic-' . $item['id'] . '-limit">' . $active_limit . '</span>';;

				echo '<p>';
					echo '<a href="#" class="wpec-lic-limit-change button-secondary" data-action="increase" data-id="' . absint( $item['id'] ) . '">+</a>';
					echo '&nbsp;<a href="#" class="wpec-lic-limit-change button-secondary" data-action="decrease" data-id="' . absint( $item['id'] ) . '">-</a>';
				echo '</p>';
				
				break;
			
			case 'expiration_date':
				return $item[ $column_name ] == 'lifetime' ? 'Lifetime' : $item[ $column_name ];
				break;
				
			case 'license_key':
			case 'url_count':
				return $item[ $column_name ];
				
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}
	
	/**
	 * Output the product name
	 */	
	function column_product_name ( $item ) {
		
		$actions = array();
		$base    = wp_nonce_url( admin_url( 'admin.php?page=wpec_license' ), 'wpec_lic_key_nonce' );
		$status  = $item['active'];
		$status_text = $item['active'] == 0 ? 'Disabled' : 'Active';
		
		$title = $item['product_name'] . ' - ' . $status_text;

		if ( $status == '1' ) {
			//Active
			$actions['deactivate'] = sprintf(
				'<a href="%s&action=%s&license=%s">' . __( 'Deactivate', 'wpec-licensing' ) . '</a>',
				$base,
				'deactivate',
				$item['id']
			 );
			$actions['renew'] = sprintf( '<a href="%s&action=%s&license=%s" title="' . __( 'Extend this license key\'s expiration date', 'wpec-licensing' ) . '">' . __( 'Extend', 'wpec-licensing' ) . '</a>', $base, 'renew', $item['id'] );
		} elseif( $status == '2' ) {
			//Expired
			$actions['renew'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Renew', 'wpec-licensing' ) . '</a>', $base, 'renew', $item['id'] );
		} else {
			//Disabled
			$actions['activate'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Activate', 'wpec-licensing' ) . '</a>', $base, 'activate', $item['id'] );
		}
		
		$actions['delete'] = sprintf( '<a href="%s&action=%s&license=%s">' . __( 'Delete', 'wpec-licensing' ) . '</a>', $base, 'delete',$item['id'] );
		
		return esc_html( $title ) . $this->row_actions( $actions );
	}
	
	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'product_name' => array( 'product_name', false ),
			'active' => array( 'active', false ),
		);
		return $sortable_columns;
	}
	
	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
	
		$actions = array(
			'deactivate'     => __( 'Deactivate', 'wpec-licensing' ),
			'activate'       => __( 'Activate', 'wpec-licensing' ),
			'renew'          => __( 'Extend', 'wpec-licensing' ),
			'delete'         => __( 'Delete', 'wpec-licensing' )
		);
		
		return $actions;

	}
	
	
	/**
	 * Process bulk actions
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */
	function process_bulk_action() {

		if( empty( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		
		if( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-licenses' ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wpec_lic_key_nonce' ) ) {
			return;
		}

		
		$ids = isset( $_GET['license'] ) ? $_GET['license'] : false;

		if( ! $ids ) {
			return;
		}

		if ( ! is_array( $ids ) ) {
			$ids = array( $ids );
		}

		foreach ( $ids as $id ) {
			// Detect when a bulk action is being triggered...

			if ( 'deactivate' === $this->current_action() ) {
				wpec_license_set_license_status( $id, 0 );
			}

			if ( 'activate' === $this->current_action() ) {
				wpec_license_set_license_status( $id, 1 );
			}

			if ( 'renew' === $this->current_action() ) {
				wpec_license_renew_license( $id );
			}

			if ( 'delete' === $this->current_action() ) {
				$this->delete_license( $id );
			}
		}

		set_transient( '_wpec_lic_bulk_actions_redirect', 1, 1000 );

	}
	

	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		add_thickbox();
		
		$columns = $this->get_columns();
		$hidden  = array(); // no hidden columns
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array( $columns, $hidden, $sortable, 'product_name' );

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();
		

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page, //WE have to determine how many items to show on a page
			'total_pages' => ceil( $total_items / $per_page )
		] );

		$this->items = self::get_licenses( $per_page, $current_page );
		
	}
}