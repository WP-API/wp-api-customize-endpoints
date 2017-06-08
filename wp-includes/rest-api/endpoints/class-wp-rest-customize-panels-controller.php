<?php
/**
 * REST API: WP_REST_Customize_Panels_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access panels via the REST API.
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
	 * Ensure customize manager.
	 *
	 * @return WP_Customize_Manager Manager.
	 * @global WP_Customize_Manager $wp_customize
	 */
	public function ensure_customize_manager() {
		global $wp_customize;
		if ( empty( $wp_customize ) ) {
			$wp_customize = new WP_Customize_Manager(); // WPCS: global override ok.
		}
		if ( ! did_action( 'customize_register' ) ) {

			/** This action is documented in wp-includes/class-wp-customize-manager.php */
			do_action( 'customize_register', $wp_customize );
		}
		$wp_customize->prepare_controls();
		return $wp_customize;
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<panel>[\w-|\[\]]+)', array(
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
				'auto_expand_sole_section' => array(
					'description' => __( 'If to auto-expand a sole section in an expanded panel.' ),
					'type'        => 'boolean',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),
				'description'     => array(
					'description' => __( 'Panel description.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'id'              => array(
					'description' => __( 'Identifier for the panel.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),
				'title'            => array(
					'description' => __( 'The title for the panel.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
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
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'theme_supports'  => array(
					'description' => __( 'Theme features required to support the panel.' ),
					'type'        => 'array',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'type'            => array(
					'description' => __( 'Type of the panel.' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
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
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$panel = $wp_customize->get_panel( $request['panel'] );
		if ( ! $panel ) {
			return new WP_Error( 'rest_panel_invalid_id', __( 'Invalid panel ID' ), array(
				'status' => 403,
			) );
		}

		return $panel->check_capabilities();
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
		$wp_customize = $this->ensure_customize_manager();

		$panel = $wp_customize->get_panel( $request['panel'] );

		return rest_ensure_response( $this->prepare_item_for_response( $panel, $request ) );
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
		$wp_customize = $this->ensure_customize_manager();

		$all_panels = $wp_customize->panels();
		$panels = array();

		foreach ( $all_panels as $panel_id => $panel ) {
			if ( ! $panel->check_capabilities() ) {
				continue;
			}
			$panels[] = $this->prepare_item_for_response( $panel, $request );
		}

		return rest_ensure_response( $panels );
	}

	/**
	 * Prepares a single panels output for response.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_Customize_Panel $panel   WP_Customize_Panel object.
	 * @param WP_REST_Request    $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $panel, $request ) {
		$data = (array) $panel;

		unset( $data['instance_count'] );
		unset( $data['instance_number'] );
		unset( $data['manager'] );
		unset( $data['active_callback'] );
		unset( $data['capability'] );

		$data['sections'] = array();

		foreach ( $panel->sections as $section_id => $section ) {
			$data['sections'][] = array(
				'section_id' => $section_id,
				'_link' => $this->namespace . '/sections/' . $section_id, // @todo Once sections endpoint has implemented, perhaps it can be taken from there.
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		/**
		 * Filters the panel data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response   $response The response object.
		 * @param WP_Customize_Panel $panel    WP_Customize_Panel object.
		 * @param WP_REST_Request    $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_panel', $data, $panel, $request );
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
