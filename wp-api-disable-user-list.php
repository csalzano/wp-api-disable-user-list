<?php
/**
 * Plugin Name: Disable User List via REST
 * Description: Prevents logged-out users from obtaining a list of user names and email addresses via the REST API.
 * Author: Breakfast Co.
 * Author URI: https://breakfastco.xyz
 * License: GPLv2
 * Version: 0.1.0
 *
 * @author Corey Salzano <csalzano@duck.com>
 * @package rest-disable-user-list
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'breakfast_rest_disable_user_list' );
/**
 * Adds a filter to user data returned by the REST API when the user making the
 * request does not have the `list_users` permission.
 *
 * @return void
 */
function breakfast_rest_disable_user_list() {
	if ( current_user_can( 'list_users' ) ) {
		return;
	}
	add_filter( 'rest_prepare_user', 'breakfast_rest_user_response', 10, 1 );
}
/**
 * Filters user data returned from the REST API.
 *
 * @param WP_REST_Response $response The response object.
 * @return WP_REST_Response
 */
function breakfast_rest_user_response( $response ) {
	$response->data  = array();
	$response->links = array();
	return $response;
}
