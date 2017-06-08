<?php
/**
 * Plugin Name: WordPress REST API endpoints for the Customizer
 * Version: 0.1.0
 * Description: Endpoints for managing changesets, settings, controls, sections, panels, partials, and anything specific to WordPress's live preview interface.
 * Author: WordPress.org
 * Plugin URI: https://github.com/WP-API/wp-api-customize-endpoints
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package WordPress
 */

/**
 * Init REST API endpoints for changesets.
 */
function wp_api_customize_andpoints_rest_init() {

	if ( ! class_exists( 'WP_REST_Customize_Changesets_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-changesets-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Controls_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-controls-controller.php';
	}

	$changesets_controller = new WP_REST_Customize_Changesets_Controller();
	$changesets_controller->register_routes();

	$controls_controller = new WP_REST_Customize_Controls_Controller();
	$controls_controller->register_routes();

}
add_action( 'rest_api_init', 'wp_api_customize_andpoints_rest_init' );
