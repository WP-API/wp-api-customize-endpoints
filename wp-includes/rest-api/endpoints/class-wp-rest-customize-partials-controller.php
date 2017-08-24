<?php
/**
 * REST API: WP_REST_Customize_Partials_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access partials via the REST API.
 *
 * @since ?.?.?
 *
 * @see WP_REST_Controller
 */
class WP_REST_Customize_Partials_Controller extends WP_REST_Customize_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'partials';
	}

	/**
	 * Registers the routes for the objects of the partialler.
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<partial>[\w-|\[\]]+)', array(
			'args' => array(
				'partial' => array(
					'description' => __( 'An alphanumeric identifier for the partial.' ),
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
	 * Retrieves the partial's schema, conforming to JSON Schema.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'partial',
			'type'       => 'object',
			'properties' => array(
				'id'              => array(
					'description' => __( 'Identifier for the partial.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'fallback_refresh' => array(
					'description' => __( 'Whether to refresh the entire preview in case a partial cannot be refreshed.' ),
					'type'        => 'boolean',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'container_inclusive' => array(
					'description' => __( 'Whether the container element is included in the partial' ),
					'type'        => 'boolean',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'selector'        => array(
					'description' => __( 'The jQuery selector to find the container element for the partial.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'settings'        => array(
					'description' => __( 'IDs for settings tied to the partial.' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'type'            => array(
					'description' => __( 'Type of the partial.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Checks if a given request has access to read a partial.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$wp_customize->selective_refresh->add_dynamic_partials( array( $request['partial'] ) );
		$partial = $wp_customize->selective_refresh->get_partial( $request['partial'] );
		if ( ! $partial ) {
			return new WP_Error( 'rest_not_found', __( 'Partial not found.' ), array(
				'status' => 404,
			) );
		}

		return $partial->check_capabilities();
	}

	/**
	 * Retrieves a single partial.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$wp_customize->selective_refresh->add_dynamic_partials( array( $request['partial'] ) );
		$partial = $wp_customize->selective_refresh->get_partial( $request['partial'] );

		return rest_ensure_response( $this->prepare_item_for_response( $partial, $request ) );
	}

	/**
	 * Checks if a given request has access to read partials.
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
	 * Retrieves list of partials.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$wp_customize = $this->ensure_customize_manager();

		$all_partials = $wp_customize->selective_refresh->partials();
		$partials = array();

		foreach ( $all_partials as $partial_id => $partial ) {
			if ( ! $partial->check_capabilities() ) {
				continue;
			}
			$data = $this->prepare_item_for_response( $partial, $request );
			$partials[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $partials );
	}

	/**
	 * Prepares a single partial's output for response.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_Customize_Partial $partial WP_Customize_Partial object.
	 * @param WP_REST_Request      $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $partial, $request ) {

		$data = array();

		$links = array(
			'self' => array(
				'href' => rest_url( trailingslashit( $this->namespace . '/' . $this->rest_base ) . $partial->id ),
			),
		);

		$hide_from_response = array(
			'capability',
			'component',
			'manager',
			'id_data',
			'primary_setting', // Hide this from response since that's returned within 'settings'.
			'render_callback',
		);

		$settings = array_unique( array_filter( array_merge( array( $partial->primary_setting ), $partial->settings ) ) );

		if ( ! empty( $settings ) ) {
			$links['related'] = array();
			$data['settings'] = array();
		}
		foreach ( $settings as $setting ) {
			$data['settings'][] = $setting;
			$links['related'][] = array(
				'href' => rest_url( trailingslashit( $this->namespace ) . 'settings/' . $setting ),
				'embeddable' => true,
			);
		}

		foreach ( $partial as $property => $value ) {
			if ( in_array( $property, $hide_from_response, true ) ) {
				continue;
			} elseif ( 'settings' === $property ) {
				continue;
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
		 * Filters the partial data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response     $response The response object.
		 * @param WP_Customize_Partial $partial  WP_Customize_Partial object.
		 * @param WP_REST_Request      $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_partial', $response, $partial, $request );
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
