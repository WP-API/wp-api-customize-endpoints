<?php
/**
 * REST API: WP_REST_Customize_Panels_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access posts via the REST API.
 *
 * @since ?.?.?
 *
 * @see WP_REST_Controller
 */
class WP_REST_Customize_Panels_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'panels';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since ?.?.?
	 * @access public
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<panel>[\w-]+)', array(
			'args' => array(
				'panel' => array(
					'description' => __( 'An alphanumeric identifier for the panel.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array(
						'default' => 'view',
					) ),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Retrieves the panel's schema, conforming to JSON Schema.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'panel',
			'type'       => 'object',
			'properties' => array(
				'description'     => array(
					'description' => __( 'Panel description.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),
				'name'            => array(
					'description' => __( 'The title for the panel.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),
				'priority'        => array(
					'description' => __( 'The priority of the panel.' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),
				'sections'        => array(
					'description' => __( 'The sections of the panel.' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'slug'            => array(
					'description' => __( 'An alphanumeric identifier for the panel.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Checks if a given request has access to read a panel.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Retrieves a single panel.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		return rest_ensure_response( '' );
	}

	/**
	 * Checks if a given request has access to read panels.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Retrieves list of panels.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		return rest_ensure_response( '' );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'context' => $this->get_context_param( array(
				'default' => 'view',
			) ),
		);
	}
}
