<?php
/**
 * REST API: WP_REST_Customize_Sections_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access sections via the REST API.
 *
 * @since ?.?.?
 *
 * @see WP_REST_Controller
 */
class WP_REST_Customize_Sections_Controller extends WP_REST_Customize_Controller {

	/**
	 * Array of sections.
	 *
	 * @since 4.?.?
	 * @access protected
	 * @var array
	 */
	protected $sections = array();

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'sections';

		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		}
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

		if ( empty( $this->sections ) ) {

			$wp_customize->prepare_controls();

			// Get all the sections like this since some sections won't be directly accessible after prepare_controls().
			$this->sections = array_merge( $wp_customize->sections() );
			foreach ( $wp_customize->panels() as $panel ) {
				$this->sections = array_merge( $this->sections, $panel->sections );
			}
		}

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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<section>[\w-|\[\]]+)', array(
			'args' => array(
				'section' => array(
					'description' => __( 'Identifier for the section.' ),
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
	 * Retrieves the section's schema, conforming to JSON Schema.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'section',
			'type'       => 'object',
			'properties' => array(
				'controls'        => array(
					'description' => __( 'The controls of the section.' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'description'     => array(
					'description' => __( 'Section description.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'description_hidden' => array(
					'description' => __( 'If to show the description of the section or not.' ),
					'type'        => 'boolean',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'id'              => array(
					'description' => __( 'Identifier for the section.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'panel'        => array(
					'description' => __( 'The related panel.' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'priority'        => array(
					'description' => __( 'The priority of the section.' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'theme_supports'  => array(
					'description' => __( 'Theme features required to support the section.' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'title'            => array(
					'description' => __( 'The title for the section.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'type'            => array(
					'description' => __( 'Type of the section.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Checks if a given request has access to read a section.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {

		$this->ensure_customize_manager();

		$section = $this->get_section( $request['section'] );
		if ( ! $section ) {
			return new WP_Error( 'rest_section_invalid_id', __( 'Invalid section ID.' ), array(
				'status' => 404,
			) );
		}

		return $section->check_capabilities();
	}

	/**
	 * Retrieves a single section.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$this->ensure_customize_manager();

		$section = $this->get_section( $request['section'] );

		return rest_ensure_response( $this->prepare_item_for_response( $section, $request ) );
	}

	/**
	 * Checks if a given request has access to read sections.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'customize' );
	}

	/**
	 * Retrieves list of sections.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$this->ensure_customize_manager();
		$sections = array();

		foreach ( $this->sections as $section_id => $section ) {
			if ( ! $section->check_capabilities() ) {
				continue;
			}
			$data = $this->prepare_item_for_response( $section, $request );
			$sections[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $sections );
	}

	/**
	 * Prepares a single section's output for response.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_Customize_Section $section WP_Customize_Section object.
	 * @param WP_REST_Request      $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $section, $request ) {

		$data = (array) $section;

		$links = array(
			'self' => array(
				'href' => rest_url( trailingslashit( $this->namespace . '/' . $this->rest_base ) . $data['id'] ),
			),
		);

		$hide_from_response = array(
			'active_callback',
			'instance_count',
			'instance_number',
			'manager',
			'capability',
		);

		$null_if_empty = array(
			'panel',
			'theme_supports',
		);

		foreach ( $data as $param => $value ) {
			if ( in_array( $param, $hide_from_response, true ) ) {
				unset( $data[ $param ] );
			} elseif ( in_array( $param, $null_if_empty, true ) ) {
				if ( empty( $value ) ) {
					$data[ $param ] = null;
				}
			}
		}

		foreach ( $hide_from_response as $param ) {
			unset( $data[ $param ] );
		}

		$data['controls'] = array();
		if ( 0 < count( $section->controls ) ) {
			$links['children'] = array();
		}

		foreach ( $section->controls as $control ) {
			$data['controls'][] = $control->id;
			$links['children'][ $control->id ] = array(
				'href' => rest_url( trailingslashit( $this->namespace ) . 'controls/' . $control->id ),
				'embeddable' => true,
			);
		}

		if ( ! empty( $data['panel'] ) ) {
			$links['up'] = array(
				'href' => rest_url( trailingslashit( $this->namespace ) . 'panels/' . $data['panel'] ),
				'embeddable' => true,
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $links );

		/**
		 * Filters the section data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response    $response The response object.
		 * @param WP_Customize_Section $section    WP_Customize_Section object.
		 * @param WP_REST_Request    $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_section', $response, $section, $request );
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

	/**
	 * Get section.
	 *
	 * @param string $id Section ID.
	 * @return WP_Customize_Section|null Section object or null.
	 */
	protected function get_section( $id ) {
		if ( isset( $this->sections[ $id ] ) ) {
			return $this->sections[ $id ];
		} else {
			return null;
		}
	}
}
