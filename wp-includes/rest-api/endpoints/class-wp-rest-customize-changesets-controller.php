<?php
/**
 * REST API: WP_REST_Posts_Controller class
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
class WP_REST_Customize_Changesets_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'changesets';

		// @todo meta?
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
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		$get_item_args = array(
			'context'  => $this->get_context_param( array(
				'default' => 'view',
			) ),
		);
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<uuid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}+)', array(
			'args' => array(
				'id' => array(
					'description' => __( 'UUID for the changeset.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => $get_item_args,
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether to bypass trash and force deletion.' ),
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Retrieves the changeset's schema, conforming to JSON Schema.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$status_enum = array_keys( get_post_stati( array(
			'internal' => false,
		) ) );
		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'customize_changeset',
			'type'       => 'object',
			'properties' => array(
				'author'          => array(
					'description' => __( 'The ID for the author of the object.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'date'            => array(
					'description' => __( "The date the object was published, in the site's timezone." ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'date_gmt'        => array(
					'description' => __( 'The date the object was published, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'settings'        => array(
					'description' => __( 'The content of the customize changeset. Changed settings in JSON format.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => null,
					),
				),
				'slug'            => array(
					'description' => __( 'Unique Customize Changeset identifier, uuid' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_slug' ),
					),
					'readonly'   => true,
				),
				'status'          => array(
					'description' => __( 'A named status for the object.' ),
					'type'        => 'string',
					'enum'        => $status_enum,
					'context'     => array( 'view', 'edit' ),
				),
				'title'           => array(
					'description' => __( 'The title for the object.' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => null,
					),
					'properties'  => array(
						'raw' => array(
							'description' => __( 'Title for the object, as it exists in the database.' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML title for the object, transformed for display.' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Checks if a given request has access to read a changeset.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {

		// @todo Check for permissions.
		return true;
	}

	/**
	 * Retrieves a single customize_changeset.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$args = array(
			'changeset_uuid' => $request['uuid'],
		);
		$customize_manager = new WP_Customize_manager( $args );
		$changeset_post = get_post( $customize_manager->changeset_post_id() );

		$data = $this->prepare_item_for_response( $changeset_post, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Retrieves the query params for customize_changesets.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['author'] = array(
			'description'         => __( 'Limit result set to posts assigned to specific authors.' ),
			'type'                => 'array',
			'items'               => array(
				'type'            => 'integer',
			),
			'default'             => array(),
		);

		$query_params['author_exclude'] = array(
			'description'         => __( 'Ensure result set excludes posts assigned to specific authors.' ),
			'type'                => 'array',
			'items'               => array(
				'type'            => 'integer',
			),
			'default'             => array(),
		);

		$query_params['slug'] = array(
			'description'       => __( 'Limit result set to posts with one or more specific slugs.' ),
			'type'              => 'array',
			'items'             => array(
				'type'          => 'string',
			),
			'sanitize_callback' => 'wp_parse_slug_list',
		);

		$query_params['status'] = array(
			'default'           => 'publish',
			'description'       => __( 'Limit result set to posts assigned one or more statuses.' ),
			'type'              => 'array',
			'items'             => array(
				'enum'          => array_merge( array_keys( get_post_stati() ), array( 'any' ) ),
				'type'          => 'string',
			),
			'sanitize_callback' => array( $this, 'sanitize_post_statuses' ),
		);

		/**
		 * Filter collection parameters for the customize_changesets controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Query parameter. Use the
		 * `rest_{$this->post_type}_query` filter to set WP_Query parameters.
		 *
		 * @since 4.?.?
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_customize_changeset_collection_params', $query_params );
	}

	/**
	 * Prepares a single customize changeset post output for response.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param WP_Post         $changeset_post    Customize changeset object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $changeset_post, $request ) {

		// Base fields for every post.
		$data = array();

		// $data['date'] = $this->prepare_date_response( $changeset_post->post_date_gmt, $changeset_post->post_date );
		/*if ( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
			$post_date_gmt = get_gmt_from_date( $post->post_date );
		} else {
			$post_date_gmt = $post->post_date_gmt;
		}
		$data['date_gmt'] = $this->prepare_date_response( $post_date_gmt );*/

		$data['slug'] = $changeset_post->post_name;
		$data['status'] = $changeset_post->post_status;

		add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
		$data['title'] = array(
			'raw'      => $changeset_post->post_title,
			'rendered' => get_the_title( $changeset_post->ID ),
		);
		remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );

		$data['settings'] = $changeset_post->post_content;

		$data['author'] = (int) $changeset_post->post_author;

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters the customize changeset data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Post          $post     Customize Changeset Post object.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_changeset', $response, $changeset_post, $request );
	}
}
