<?php
/**
 * Plugin Name: Disable User List via REST
 * Description: Prevents user enumeration via the REST API, XML-RPC, author archives, and login errors.
 * Plugin URI: https://github.com/csalzano/wp-api-disable-user-list/
 * Author: Breakfast
 * Author URI: https://breakfastco.xyz
 * License: GPLv2
 * Version: 0.3.0
 * GitHub Plugin URI: https://github.com/csalzano/wp-api-disable-user-list
 * Primary Branch: main
 *
 * @author Corey Salzano <csalzano@duck.com>
 * @package wp-api-disable-user-list
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'breakfast_rest_disable_user_list' );
/**
 * Prevents unauthenticated (or unauthorized) user enumeration via REST.
 *
 * Covers:
 *  - GET and HEAD /wp/v2/users and /wp/v2/users/{id}
 *  - Author slug and name embedded in ?_embed responses for all REST-enabled
 *    post types that support the author field
 *
 * @return void
 */
function breakfast_rest_disable_user_list() {
	if ( current_user_can( 'list_users' ) ) {
		return;
	}

	add_filter(
		'rest_pre_dispatch',
		function ( $result, $server, $request ) {
			if ( ! ( $request instanceof WP_REST_Request ) ) {
				return $result;
			}

			$route  = (string) $request->get_route();
			$method = (string) $request->get_method();

			// Block GET and HEAD on the users collection and individual user endpoints.
			if (
				in_array( $method, array( 'GET', 'HEAD' ), true )
				&& ( '/wp/v2/users' === $route || 0 === strpos( $route, '/wp/v2/users/' ) )
			) {
				return new WP_Error(
					'rest_forbidden',
					__( 'Sorry, you are not allowed to list users.', 'wp-api-disable-user-list' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			return $result;
		},
		10,
		3
	);

	// Strip author data from REST responses for every REST-enabled post type that supports authors.
	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		if ( post_type_supports( $post_type->name, 'author' ) ) {
			add_filter( "rest_prepare_{$post_type->name}", 'breakfast_rest_scrub_embedded_author', 10, 3 );
		}
	}
}

/**
 * Removes author slug, link, and name from REST responses so that
 * ?_embed does not leak user_nicename to unauthenticated callers.
 *
 * @param WP_REST_Response $response
 * @param WP_Post          $post
 * @param WP_REST_Request  $request
 * @return WP_REST_Response
 */
function breakfast_rest_scrub_embedded_author( $response, $post, $request ) {
	$data = $response->get_data();

	// Remove the author link so the client cannot follow it to /wp/v2/users/{id}.
	$links = $response->get_links();
	if ( isset( $links['author'] ) ) {
		$response->remove_link( 'author' );
	}

	// Replace the numeric author ID with 0 to prevent direct lookup.
	if ( isset( $data['author'] ) ) {
		$data['author'] = 0;
		$response->set_data( $data );
	}

	return $response;
}

// Block author enumeration via both /?author=N and /author/slug/ URLs.
add_action( 'template_redirect', 'breakfast_block_author_enumeration' );
/**
 * Returns a 403 for any author query made by unauthenticated visitors,
 * covering both the /?author=N redirect and direct /author/slug/ archives.
 *
 * @return void
 */
function breakfast_block_author_enumeration() {
	if ( current_user_can( 'list_users' ) ) {
		return;
	}

	if ( ! is_admin() && ( isset( $_GET['author'] ) || is_author() ) ) {
		wp_die(
			esc_html__( 'Author archives are not available.', 'wp-api-disable-user-list' ),
			esc_html__( 'Forbidden', 'wp-api-disable-user-list' ),
			array( 'response' => 403 )
		);
	}
}

add_filter( 'xmlrpc_methods', 'breakfast_xmlrpc_disable_user_methods' );
/**
 * Replaces XML-RPC user-listing methods with a stub that always returns a
 * 403 fault, preventing enumeration via wp.getAuthors, wp.getUsers, and
 * wp.getUser regardless of the caller's credentials.
 *
 * Note: xmlrpc_methods fires before authentication, so a capability check is
 * not possible here. These methods are blocked unconditionally.
 *
 * @param array $methods
 * @return array
 */
function breakfast_xmlrpc_disable_user_methods( $methods ) {
	foreach ( array( 'wp.getAuthors', 'wp.getUsers', 'wp.getUser' ) as $method ) {
		$methods[ $method ] = 'breakfast_xmlrpc_user_method_forbidden';
	}
	return $methods;
}

/**
 * XML-RPC handler stub for blocked user-listing methods.
 *
 * @return IXR_Error
 */
function breakfast_xmlrpc_user_method_forbidden() {
	return new IXR_Error( 403, __( 'Sorry, you are not allowed to list users.', 'wp-api-disable-user-list' ) );
}

add_filter( 'login_errors', 'breakfast_generic_login_error' );
/**
 * Replaces WordPress login error messages with a generic string so that
 * responses do not reveal whether a submitted username is registered on
 * this site.
 *
 * @return string
 */
function breakfast_generic_login_error() {
	return __( '<strong>Error:</strong> The username or password you entered is incorrect.', 'wp-api-disable-user-list' );
}
