<?php
/**
 * Plugin Name: Disable User List via REST
 * Description: Prevents logged-out users from obtaining a list of user names via the REST API.
 * Plugin URI: https://github.com/csalzano/wp-api-disable-user-list/
 * Author: Breakfast Co.
 * Author URI: https://breakfastco.xyz
 * License: GPLv2
 * Version: 0.1.2
 *
 * @author Corey Salzano <csalzano@duck.com>
 * @package wp-api-disable-user-list
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'breakfast_rest_disable_user_list' );
/**
 * Prevents unauthenticated (or unauthorized) user enumeration via REST.
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

			// Block the users collection and individual user endpoints for unauthorized requests.
			if (
				'GET' === $method
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
}
