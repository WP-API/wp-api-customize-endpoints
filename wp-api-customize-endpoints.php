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
function wp_api_customize_endpoints_rest_init() {

	if ( ! class_exists( 'WP_REST_Customize_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Changesets_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-changesets-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Settings_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-settings-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Panels_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-panels-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Controls_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-controls-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Sections_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-sections-controller.php';
	}

	if ( ! class_exists( 'WP_REST_Customize_Partials_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/wp-includes/rest-api/endpoints/class-wp-rest-customize-partials-controller.php';
	}

	$changesets_controller = new WP_REST_Customize_Changesets_Controller();
	$changesets_controller->register_routes();

	$controls_controller = new WP_REST_Customize_Controls_Controller();
	$controls_controller->register_routes();

	$panels_controller = new WP_REST_Customize_Panels_Controller();
	$panels_controller->register_routes();

	$sections_controller = new WP_REST_Customize_Sections_Controller();
	$sections_controller->register_routes();

	$settings_controller = new WP_REST_Customize_Settings_Controller();
	$settings_controller->register_routes();

	$partials_controller = new WP_REST_Customize_Partials_Controller();
	$partials_controller->register_routes();
}
add_action( 'rest_api_init', 'wp_api_customize_endpoints_rest_init' );

/**
 * Temporary abstraction of changeset-trashing logic.
 *
 * @see https://github.com/WP-API/wp-api-customize-endpoints/pull/5#discussion_r120015044.
 *
 * @param int $post_id Post ID.
 */
function _wp_customize_trash_changeset( $post_id ) {
	global $wpdb;

	$post = get_post( $post_id );

	if ( ! $post ) {
		return;
	}

	/** This action is documented in wp-includes/post.php */
	do_action( 'wp_trash_post', $post_id );

	add_post_meta( $post_id, '_wp_trash_meta_status', $post->post_status );
	add_post_meta( $post_id, '_wp_trash_meta_time', time() );

	$old_status = $post->post_status;
	$new_status = 'trash';

	$wpdb->update(
		$wpdb->posts,
		array(
			'post_status' => $new_status,
		),
		array(
			'ID' => $post_id,
		)
	); // WPCS: db call ok.

	clean_post_cache( $post_id );

	$post->post_status = $new_status;
	wp_transition_post_status( $new_status, $old_status, $post );

	/** This action is documented in wp-includes/post.php */
	do_action( 'edit_post', $post_id, $post );

	/** This action is documented in wp-includes/post.php */
	do_action( "save_post_{$post->post_type}", $post_id, $post, true );

	/** This action is documented in wp-includes/post.php */
	do_action( 'save_post', $post_id, $post, true );

	/** This action is documented in wp-includes/post.php */
	do_action( 'wp_insert_post', $post_id, $post, true );

	/** This action is documented in wp-includes/post.php */
	do_action( 'trashed_post', $post_id );
}
