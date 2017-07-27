<?php
/**
 * REST API: WP_REST_Customize_Controls_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access controls via the REST API.
 *
 * @since ?.?.?
 *
 * @see WP_REST_Controller
 */
class WP_REST_Customize_Controls_Controller extends WP_REST_Customize_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'controls';
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<control>[\w-]+)', array(
			'args' => array(
				'control' => array(
					'description' => __( 'An alphanumeric identifier for the control.' ),
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
	 * Retrieves the control's schema, conforming to JSON Schema.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'control',
			'type'       => 'object',
			'properties' => array(
				'allow_addition'  => array(
					'description' => __( 'If to show UI for adding new content' ),
					'type'        => 'boolean',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'choices'         => array(
					'description' => __( 'List of choices for radio/select.' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'description'     => array(
					'description' => __( 'Control description.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'id'              => array(
					'description' => __( 'Identifier for the control.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'input_attrs'         => array(
					'description' => __( 'Input attributes for a control.' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'label'           => array(
					'description' => __( 'Label for the control.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'priority'        => array(
					'description' => __( 'The priority of the control.' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'section'        => array(
					'description' => __( 'The related section.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'settings'        => array(
					'description' => __( 'The settings tied to the control.' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'type'            => array(
					'description' => __( 'Type of the control.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Checks if a given request has access to read a control.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$control = $wp_customize->get_control( $request['control'] );
		if ( ! $control ) {
			return new WP_Error( 'rest_control_invalid_id', __( 'Invalid control ID.' ), array(
				'status' => 404,
			) );
		}

		return $control->check_capabilities();
	}

	/**
	 * Retrieves a single control.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$control = $wp_customize->get_control( $request['control'] );

		return rest_ensure_response( $this->prepare_item_for_response( $control, $request ) );
	}

	/**
	 * Checks if a given request has access to read controls.
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
	 * Retrieves list of controls.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$all_controls = $wp_customize->controls();
		$controls = array();

		foreach ( $all_controls as $control_id => $control ) {
			/**
			 * Control.
			 *
			 * @var WP_Customize_Control $control
			 */
			if ( ! $control->check_capabilities() ) {
				continue;
			}
			$data = $this->prepare_item_for_response( $control, $request );
			$controls[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $controls );
	}

	/**
	 * Prepares a single control's output for response.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_Customize_Control $control WP_Customize_Control object.
	 * @param WP_REST_Request      $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $control, $request ) {

		$control_array = (array) $control;
		$data = array();
		$primary_setting = '';

		$links = array(
			'self' => array(
				'href' => rest_url( trailingslashit( $this->namespace . '/' . $this->rest_base ) . $control_array['id'] ),
			),
		);

		$hide_from_response = array(
			'capability',
			'instance_number',
			'manager',
			'json',
			'setting', // Hide this from response since that's returned within 'settings'.
			'active_callback',
		);

		$null_if_empty = array(
			'section',
		);

		// Remove unused params of upload control.
		if ( $control instanceof WP_Customize_Upload_Control ) {
			$hide_from_response[] = 'removed';
			$hide_from_response[] = 'context';
			$hide_from_response[] = 'extensions';
		}

		if ( ! empty( $control_array['section'] ) ) {
			$links['up'] = array(
				'href' => rest_url( trailingslashit( $this->namespace ) . 'sections/' . $control_array['section'] ),
				'embeddable' => true,
			);
		}

		// Get primary setting ID.
		if ( is_object( $control_array['setting'] ) ) {
			$primary_setting = $control_array['setting']->id;
			$data['settings'] = array( $primary_setting );
			$links['related'] = array( array(
					'href' => rest_url( trailingslashit( $this->namespace ) . 'settings/' . $primary_setting ),
					'embeddable' => true,
				),
			);
		}

		foreach ( $control_array as $property => $value ) {
			if ( in_array( $property, $hide_from_response, true ) ) {
				continue;
			} elseif ( in_array( $property, $null_if_empty, true ) ) {
				if ( empty( $value ) ) {
					$data[ $property ] = null;
				} else {
					$data[ $property ] = $value;
				}
			} elseif ( 'settings' === $property ) {
				if ( ! empty( $value ) && empty( $primary_setting ) ) {
					$links['related'] = array();
					$data['settings'] = array();
				}
				foreach ( $value as $name => $setting ) {
					if ( is_object( $setting ) ) {

						$link_data = array(
							'href' => rest_url( trailingslashit( $this->namespace ) . 'settings/' . $setting->id ),
							'embeddable' => true,
						);

						// Skip since that was added separately before.
						if ( $primary_setting === $setting->id ) {
							continue;
						} else {
							$data['settings'][] = $setting->id;
							$links['related'][] = $link_data;
						}
					}
				}
			} else {
				$data[ $property ] = $value;
			}
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $links );

		/**
		 * Filters the control data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response     $response The response object.
		 * @param WP_Customize_Control $control    WP_Customize_Control object.
		 * @param WP_REST_Request      $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_control', $response, $control, $request );
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
