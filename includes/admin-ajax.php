<?php
// retrieves a list of users via live search
function license_search_users() {

	if( wp_verify_nonce( $_POST['license_nonce'], 'license_member_nonce' ) ) {

		$search_query = trim( $_POST['user_name'] );

		$found_users = get_users( array(
				'number' => 9999,
				'search' => $search_query . '*'
			)
		);

		if( $found_users ) {
			$user_list = '<ul>';
				foreach( $found_users as $user ) {
					$user_list .= '<li><a href="#" data-login-name="' . esc_attr( $user->user_login ) . '" data-login-id="' . esc_attr( $user->ID ) . '">' . esc_html( $user->user_login ) . '</a></li>';
				}
			$user_list .= '</ul>';

			echo json_encode( array( 'results' => $user_list, 'id' => 'found' ) );

		} else {
			echo json_encode( array( 'msg' => '<ul><li>' . __( 'No users found', 'wpec_license' ) . '</li></ul>', 'results' => 'none', 'id' => 'fail' ) );
		}

	}
	die();
}
add_action( 'wp_ajax_license_search_users', 'license_search_users' );

function license_search_products() {

	if( wp_verify_nonce( $_POST['license_nonce'], 'license_product_nonce' ) ) {

		$search_query = trim( $_POST['product_search'] );

		
		$found_posts = get_posts( array(
				's' => $search_query,
				'post_type' => 'wpsc-product',
				'posts_per_page' => -1,
				'post_status'	=> array( 'publish', 'inherit' )
			)
		);
		
		if( $found_posts ) {
			$product_list = '<ul>';
				foreach( $found_posts as $post ) {
					$product_list .= '<li><a href="#" data-product-id="' . esc_attr( $post->ID ) . '" data-product-name="' . esc_attr( $post->post_title ) . '">' . esc_html( $post->post_title ) . '</a></li>';
				}
			$product_list .= '</ul>';

			echo json_encode( array( 'results' => $product_list, 'id' => 'found' ) );

		} else {
			echo json_encode( array( 'msg' => '<ul><li>' . __( 'No products found', 'wpec_license' ) . '</li></ul>', 'results' => 'none', 'id' => 'fail' ) );
		}

	}
	die();
}
add_action( 'wp_ajax_license_search_products', 'license_search_products' );