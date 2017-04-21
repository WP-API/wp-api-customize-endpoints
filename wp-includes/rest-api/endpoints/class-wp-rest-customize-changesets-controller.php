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
	 * Post type.
	 *
	 * @since 4.?.?
	 * @access protected
	 * @var string
	 */
	protected $post_type = 'customize_changeset';

	/**
	 * WP Customize Manager.
	 *
	 * @since 4.?.?
	 * @access protected
	 * @var WP_Customize_Manager
	 */
	protected $manager;

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'changesets';

		$this->manager = new WP_Customize_Manager();
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
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
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

		$post_type_obj = get_post_type_object( $this->post_type );
		$changeset_post = $this->get_customize_changeset_post( $request['uuid'] );
		if ( ! $changeset_post ) {
			return false;
		}
		$data = array();
		if ( isset( $request['customize_changeset_data'] ) ) {
			$data = $request['customize_changeset_data'];
		}

		if ( 'edit' === $request['context'] && $changeset_post && ! $this->check_update_permission( $changeset_post, $data ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit this post.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		return $this->check_read_permission( $post_type_obj, $changeset_post );
	}

	/**
	 * Check if current user can read the changeset.
	 *
	 * @param object $post_type_obj Post type object.
	 * @param object $changeset_post Changeset post object.
	 * @return bool If has read permissions.
	 */
	protected function check_read_permission( $post_type_obj, $changeset_post ) {
		return current_user_can( $post_type_obj->cap->read_post, $changeset_post->ID );
	}

	/**
	 * Check if user has permissions to edit all the values.
	 *
	 * @param object $changeset_post Changeset post object.
	 * @param array  $data Array of data to change.
	 * @return bool If has permissions.
	 */
	protected function check_update_permission( $changeset_post, $data ) {
		$post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->edit_post, $changeset_post->ID ) ) {
			return false;
		}

		// Check permissions per setting.
		foreach ( $data as $setting_id => $params ) {
			$setting = $this->manager->get_setting( $setting_id );
			if ( ! $setting->check_capabilities() ) {
				return false;
			}
		}
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

		$changeset_post = $this->get_customize_changeset_post( $request['uuid'] );
		$data = $this->prepare_item_for_response( $changeset_post, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Checks if a given request has access to read changeset posts.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {

		$post_type = get_post_type_object( $this->post_type );

		if ( 'edit' === $request['context'] && ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit posts in this post type.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		return current_user_can( $post_type->cap->read_post );
	}

	/**
	 * Retrieves multiple customize changesets.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		// Ensure a search string is set in case the orderby is set to 'relevance'.
		if ( ! empty( $request['orderby'] ) && 'relevance' === $request['orderby'] && empty( $request['search'] ) ) {
			return new WP_Error( 'rest_no_search_term_defined', __( 'You need to define a search term to order by relevance.' ), array(
				'status' => 400,
			) );
		}

		$registered = $this->get_collection_params();

		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'search'         => 's',
			'status'         => 'post_status',
		);

		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Ensure per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		$args['post_type'] = $this->post_type;

		/**
		 * Filters the query arguments for a request.
		 *
		 * Enables adding extra arguments or setting defaults for a post collection request.
		 *
		 * @since 4.?.?
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_query/
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 */
		$args = apply_filters( "rest_{$this->post_type}_query", $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$changesets_query  = new WP_Query();
		$query_result = $changesets_query->query( $query_args );

		$changesets = array();
		foreach ( $query_result as $changeset_post ) {
			if ( ! $this->check_read_permission( get_post_type_object( $this->post_type ), $changeset_post ) ) {
				continue;
			}

			$data         = $this->prepare_item_for_response( $changeset_post, $request );
			$changesets[] = $this->prepare_response_for_collection( $data );
		}

		$page = (int) $query_args['paged'];
		$total_posts = $changesets_query->found_posts;

		if ( $total_posts < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $query_args['paged'] );

			$count_query = new WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}

		$max_pages = ceil( $total_posts / (int) $changesets_query->query_vars['posts_per_page'] );

		if ( $page > $max_pages && $total_posts > 0 ) {
			return new WP_Error( 'rest_post_invalid_page_number', __( 'The page number requested is larger than the number of pages available.' ), array(
				'status' => 400,
			) );
		}

		$response  = rest_ensure_response( $changesets );

		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$request_params = $request->get_query_params();
		$base = add_query_arg( $request_params, rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Determines the allowed query_vars for a get_items() response and prepares
	 * them for WP_Query.
	 *
	 * @since 4.?.?
	 * @access protected
	 *
	 * @param array           $prepared_args Optional. Prepared WP_Query arguments. Default empty array.
	 * @param WP_REST_Request $request       Optional. Full details about the request.
	 * @return array Items query arguments.
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {
		$query_args = array();

		foreach ( $prepared_args as $key => $value ) {
			$query_args[ $key ] = $value;
		}

		$query_args['ignore_sticky_posts'] = true;

		// Map to proper WP_Query orderby param.
		if ( isset( $query_args['orderby'] ) && isset( $request['orderby'] ) ) {
			$orderby_mappings = array(
				'id'   => 'ID',
				'slug' => 'post_name',
			);

			if ( isset( $orderby_mappings[ $request['orderby'] ] ) ) {
				$query_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
			}
		}

		return $query_args;
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

		$query_params['status'] = array(
			'default'           => 'publish',
			'description'       => __( 'Limit result set to posts assigned one or more statuses.' ),
			'type'              => 'array',
			'items'             => array(
				'enum'          => array_merge( array_keys( get_post_stati() ), array( 'any' ) ),
				'type'          => 'string',
			),
			'sanitize_callback' => array( $this, 'sanitize_post_statuses' ),
			'default'           => array( 'auto-draft' ),
		);

		$query_params['offset'] = array(
			'description'        => __( 'Offset the result set by a specific number of items.' ),
			'type'               => 'integer',
		);

		$query_params['order'] = array(
			'description'        => __( 'Order sort attribute ascending or descending.' ),
			'type'               => 'string',
			'default'            => 'desc',
			'enum'               => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
			'description'        => __( 'Sort collection by object attribute.' ),
			'type'               => 'string',
			'default'            => 'date',
			'enum'               => array(
				'date',
				'relevance',
				'id',
				'title',
				'slug',
			),
		);

		/**
		 * Filter collection parameters for the customize_changesets controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Query parameter.
		 *
		 * @since 4.?.?
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( "rest_{$this->post_type}_collection_params", $query_params );
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

		$data = array();

		$data['date'] = $this->prepare_date_response( $changeset_post->post_date_gmt, $changeset_post->post_date );
		if ( '0000-00-00 00:00:00' === $changeset_post->post_date_gmt ) {
			$post_date_gmt = get_gmt_from_date( $changeset_post->post_date );
		} else {
			$post_date_gmt = $changeset_post->post_date_gmt;
		}
		$data['date_gmt'] = $this->prepare_date_response( $post_date_gmt );

		$data['slug'] = $changeset_post->post_name;
		$data['status'] = $changeset_post->post_status;

		add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
		$data['title'] = array(
			'raw'      => $changeset_post->post_title,
			'rendered' => get_the_title( $changeset_post->ID ),
		);
		remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );

		$raw_settings = json_decode( $changeset_post->post_content, true );
		$settings = array();

		foreach ( $raw_settings as $setting_id => $params ) {
			$setting = $this->manager->get_setting( $setting_id );
			if ( ! $setting->check_capabilities() ) {
				continue;
			}
			$settings[ $setting_id ] = $params['value'];
		}

		$data['settings'] = $settings;

		$data['author'] = (int) $changeset_post->post_author;

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		/**
		 * Filters the customize changeset data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Post          $post     Customize Changeset Post object.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( 'rest_prepare_customize_changeset', $data, $changeset_post, $request );
	}

	/**
	 * Checks the post_date_gmt or and prepare for single post output.
	 *
	 * @since 4.?.?
	 * @access protected
	 *
	 * @param string      $date_gmt GMT publication time.
	 * @param string|null $date     Optional. Local publication time. Default null.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {

		// Use the date if passed.
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		// Return null if $date_gmt is empty/zeros.
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		// Return the formatted datetime.
		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Get customize changeset post object.
	 *
	 * @param string $uuid Changeset UUID.
	 * @return array|null|WP_Post Post object.
	 */
	protected function get_customize_changeset_post( $uuid ) {
		$args = array(
			'changeset_uuid' => $uuid,
		);
		$customize_manager = new WP_Customize_manager( $args );
		return get_post( $customize_manager->changeset_post_id() );
	}

	/**
	 * Sanitizes and validates the list of post statuses, including whether the
	 * user can query private statuses.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param  string|array    $statuses  One or more post statuses.
	 * @param  WP_REST_Request $request   Full details about the request.
	 * @param  string          $parameter Additional parameter to pass to validation.
	 * @return array|WP_Error A list of valid statuses, otherwise WP_Error object.
	 */
	public function sanitize_post_statuses( $statuses, $request, $parameter ) {
		$statuses = wp_parse_slug_list( $statuses );

		$default_status = 'auto-draft';

		foreach ( $statuses as $status ) {
			if ( $status === $default_status ) {
				continue;
			}

			$post_type_obj = get_post_type_object( $this->post_type );

			if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
				$result = rest_validate_request_arg( $status, $request, $parameter );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} else {
				return new WP_Error( 'rest_forbidden_status', __( 'Status is forbidden.' ), array(
					'status' => rest_authorization_required_code(),
				) );
			}
		}

		return $statuses;
	}
}
