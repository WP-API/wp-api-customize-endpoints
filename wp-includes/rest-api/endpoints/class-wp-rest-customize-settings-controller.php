<?php
/**
 * REST API: WP_REST_Customize_Settings_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access settings via the REST API.
 *
 * @since ?.?.?
 *
 * @see WP_REST_Controller
 */
class WP_REST_Customize_Settings_Controller extends WP_REST_Customize_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'settings';
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
				'args'                => $this->get_collection_params(),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<setting>[\w-|\[\]]+)', array(
			'args' => array(
				'setting' => array(
					'description' => __( 'Identifier for the setting.' ),
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
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Retrieves the setting's schema, conforming to JSON Schema.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'setting',
			'type'       => 'object',
			'properties' => array(
				'default'         => array(
					'description' => __( 'Default value' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'dirty'           => array(
					'description' => __( 'Whether or not the setting is dirty.' ),
					'type'        => 'boolean',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'id'              => array(
					'description' => __( 'Identifier for the setting.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'theme_supports'  => array(
					'description' => __( 'Theme features required to support the setting.' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'transport'       => array(
					'description' => __( 'Options for rendering the live preview.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'type'            => array(
					'description' => __( 'Type of the setting.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'value'           => array(
					'description' => __( 'Setting value.' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Checks if a given request has access to read a setting.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$wp_customize->add_dynamic_settings( array( $request['setting'] ) );
		$setting = $wp_customize->get_setting( $request['setting'] );
		if ( ! $setting ) {
			return new WP_Error( 'rest_setting_invalid_id', __( 'Invalid setting ID.' ), array(
				'status' => 404,
			) );
		}

		return $setting->check_capabilities();
	}

	/**
	 * Retrieves a single setting.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$wp_customize->add_dynamic_settings( array( $request['setting'] ) );
		$setting = $wp_customize->get_setting( $request['setting'] );

		return rest_ensure_response( $this->prepare_item_for_response( $setting, $request ) );
	}

	/**
	 * Checks if a given request has access to read settings.
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
	 * Retrieves list of settings.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$all_settings = $wp_customize->settings();
		$settings = array();

		foreach ( $all_settings as $setting_id => $setting ) {
			/**
			 * Setting.
			 *
			 * @var WP_Customize_Setting $setting
			 */
			if ( ! $setting->check_capabilities() ) {
				continue;
			}

			$data = $this->prepare_item_for_response( $setting, $request );
			$settings[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $settings );
	}

	/**
	 * Checks if a given request has access to update settings.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$wp_customize->add_dynamic_settings( array( $request['setting'] ) );
		$setting = $wp_customize->get_setting( $request['setting'] );
		if ( ! $setting ) {
			return new WP_Error( 'rest_setting_not_found', __( 'Setting not found.' ), array(
				'status' => 404,
			) );
		}

		return $setting->check_capabilities();
	}

	/**
	 * Updates a setting value.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$wp_customize = $this->ensure_customize_manager();
		$wp_customize->add_dynamic_settings( array( $request['setting'] ) );

		$wp_customize->set_post_value( $request['setting'], $request['value'] );
		$setting = $wp_customize->get_setting( $request['setting'] );
		$setting->save();

		return rest_ensure_response( $this->prepare_item_for_response( $setting, $request ) );
	}

	/**
	 * Prepares a single setting's output for response.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_Customize_Setting $setting WP_Customize_Setting object.
	 * @param WP_REST_Request      $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $setting, $request ) {
		$data = array();

		$schema = $this->get_item_schema();

		$null_if_empty = array(
			'theme_supports',
		);

		foreach ( $schema['properties'] as $name => $params ) {
			if ( 'value' === $name ) {
				$data[ $name ] = $setting->value();
			} elseif ( in_array( $name, $null_if_empty, true ) ) {
				if ( empty( $setting->{$name} ) ) {
					$data[ $name ] = null;
				} else {
					$data[ $name ] = $setting->{$name};
				}
			} else {
				$data[ $name ] = $setting->{$name};
			}
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$links = array(
			'self' => array(
				'href' => rest_url( trailingslashit( $this->namespace . '/' . $this->rest_base ) . $data['id'] ),
			),
		);

		$response = rest_ensure_response( $data );
		$response->add_links( $links );

		/**
		 * Filters the setting data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response     $response The response object.
		 * @param WP_Customize_Setting $setting  WP_Customize_Setting object.
		 * @param WP_REST_Request      $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_setting', $response, $setting, $request );
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
