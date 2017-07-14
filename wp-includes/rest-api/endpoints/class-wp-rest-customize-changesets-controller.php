<?php
/**
 * REST API: WP_REST_Customize_Changesets_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since ?.?.?
 */

/**
 * Core class to access customize changesets via the REST API.
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
	 * Allowed changeset statuses.
	 *
	 * @since 4.?.?
	 * @var array
	 */
	protected $statuses = array(
		'auto-draft',
		'draft',
		'future',
		'pending',
		'publish',
	);

	const REGEX_CHANGESET_UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'customize/v1';
		$this->rest_base = 'changesets';

		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		}
	}

	/**
	 * Ensure customize manager.
	 *
	 * @param string $changeset_uuid UUID.
	 * @return WP_Customize_Manager Manager.
	 * @global WP_Customize_Manager $wp_customize
	 */
	public function ensure_customize_manager( $changeset_uuid = null ) {
		global $wp_customize;

		if ( ! ( $wp_customize instanceof WP_Customize_Manager ) || $wp_customize->changeset_uuid() !== $changeset_uuid ) {
			$settings_previewed = false;
			$wp_customize = new WP_Customize_Manager( compact( 'changeset_uuid', 'settings_previewed' ) ); // WPCS: global override ok.

			/** This action is documented in wp-includes/class-wp-customize-manager.php */
			do_action( 'customize_register', $wp_customize );
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
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<uuid>' . self::REGEX_CHANGESET_UUID . '+)', array(
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
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_datetime' ),
					),
				),
				'date_gmt'        => array(
					'description' => __( 'The date the object was published, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_datetime' ),
					),
				),
				'settings'        => array(
					'description' => __( 'The content of the customize changeset. Changed settings in JSON format.' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
				'status'          => array(
					'description' => __( 'A named status for the object.' ),
					'type'        => 'string',
					'enum'        => $this->statuses,
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_post_status' ),
					),
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
				'uuid'            => array(
					'description' => __( 'Unique Customize Changeset identifier, uuid' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_uuid' ),
					),
					'readonly'   => true,
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
			return new WP_Error( 'rest_post_invalid_uuid', __( 'Invalid changeset UUID.' ), array(
				'status' => 404,
			) );
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
	 * @param WP_Post_Type $post_type_obj  Post type object.
	 * @param WP_Post      $changeset_post Changeset post object.
	 * @return bool If has read permissions.
	 */
	protected function check_read_permission( $post_type_obj, $changeset_post ) {
		return current_user_can( $post_type_obj->cap->read_post, $changeset_post->ID );
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

		$manager = $this->ensure_customize_manager( $request['uuid'] );
		$post_id = $manager->changeset_post_id();
		if ( ! $post_id ) {
			return new WP_Error( 'rest_post_invalid_uuid', __( 'Invalid changeset UUID.' ), array(
				'status' => 404,
			) );
		}

		$data = $this->prepare_item_for_response( get_post( $post_id ), $request );
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
		if ( isset( $request['per_page'] ) ) {
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
	 * Checks if a given request has access to update a changeset.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$changeset_post = $this->get_customize_changeset_post( $request['uuid'] );
		if ( ! $changeset_post ) {
			return $this->create_item_permissions_check( $request );
		}

		if ( $this->is_published_changeset( $changeset_post ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, the customize changeset is already published.' ), array(
				'status' => 403,
			) );
		}

		if ( isset( $request['status'] ) && 'auto-draft' === $request['status'] ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, invalid status.' ), array(
				'status' => 403,
			) );
		}

		$data = array();
		if ( isset( $request['customize_changeset_data'] ) ) {
			$data = $request['customize_changeset_data'];
		}

		if ( ! $this->check_update_permission( $changeset_post, $data ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to update this changeset post.' ), array(
				'status' => 403,
			) );
		}

		$post_type_obj = get_post_type_object( $this->post_type );

		if ( ! empty( $request['author'] ) && get_current_user_id() !== $request['author'] && ! current_user_can( $post_type_obj->cap->edit_others_posts ) ) {
			return new WP_Error( 'rest_cannot_edit_others', __( 'Sorry, you are not allowed to update changeset posts as this user.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		return true;
	}

	/**
	 * Check if user has permissions to edit all the values.
	 *
	 * @param WP_Post $changeset_post Changeset post object.
	 * @param array   $data Array of data to change.
	 * @return bool If has permissions.
	 */
	protected function check_update_permission( $changeset_post, $data ) {
		$post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->edit_post, $changeset_post->ID ) ) {
			return false;
		}
		$manager = $this->ensure_customize_manager( $changeset_post->post_name );

		// Check permissions per setting.
		foreach ( $data as $setting_id => $params ) {
			$setting = $manager->get_setting( $setting_id );
			if ( ! $setting || ! $setting->check_capabilities() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Updates a single changeset post.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response WP_Error or REST response.
	 */
	public function update_item( $request ) {

		$existing_post = $this->get_customize_changeset_post( $request['uuid'] );
		if ( ! $existing_post ) {
			return $this->create_item( $request );
		}

		$changeset_post = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $changeset_post ) ) {
			return $changeset_post;
		}

		// convert the post object to an array, otherwise wp_update_post will expect non-escaped input.
		$post_id = wp_update_post( wp_slash( (array) $changeset_post ), true );

		if ( is_wp_error( $post_id ) ) {
			if ( 'db_update_error' === $post_id->get_error_code() ) {
				$post_id->add_data( array(
					'status' => 500,
				) );
			} else {
				$post_id->add_data( array(
					'status' => 400,
				) );
			}
			return $post_id;
		}

		$changeset_post = get_post( $post_id );

		/* This action is documented in lib/endpoints/class-wp-rest-controller.php */
		do_action( "rest_insert_{$this->post_type}", $changeset_post, $request, false );

		$fields_update = $this->update_additional_fields_for_object( $changeset_post, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $changeset_post, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Checks if a given request has access to create a changeset post.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! empty( $request['uuid'] ) ) {

			$valid_uuid = $this->sanitize_uuid( $request['uuid'] );
			if ( is_wp_error( $valid_uuid ) ) {
				return $valid_uuid;
			}
			$existing_post = $this->get_customize_changeset_post( $request['uuid'] );
			if ( $existing_post ) {
				if ( $this->is_published_changeset( $existing_post ) ) {

					return new WP_Error( 'rest_cannot_create', __( 'Sorry, changeset post is already published.' ), array(
						'status' => rest_authorization_required_code(),
					) );

				}
				return $this->update_item_permissions_check( $request );
			}
		}

		$post_type = get_post_type_object( $this->post_type );

		if ( ! empty( $request['author'] ) && get_current_user_id() !== $request['author'] && ! current_user_can( $post_type->cap->edit_others_posts ) ) {
			return new WP_Error( 'rest_cannot_edit_others', __( 'Sorry, you are not allowed to create posts as this user.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		if ( isset( $request['slug'] ) ) {
			return new WP_Error( 'cannot_edit_changeset_slug', __( 'Not allowed to edit changeset slug' ), array(
				'status' => 403,
			) );
		}

		if ( ! current_user_can( $post_type->cap->create_posts ) ) {
			return new WP_Error( 'rest_cannot_create', __( 'Sorry, you are not allowed to create posts as this user.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		return true;
	}

	/**
	 * Creates a single changeset post.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {

		if ( ! isset( $request['uuid'] ) ) {
			$request['uuid'] = wp_generate_uuid4();
		} else {
			$existing_post = $this->get_customize_changeset_post( $request['uuid'] );
			if ( $existing_post ) {
				return $this->update_item( $request );
			}
		}

		$prepared_post = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		// Special case for publishing.
		$is_publish = ( 'publish' === $prepared_post->post_status );
		if ( ( $is_publish || 'future' === $prepared_post->post_status ) && ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->publish_posts ) ) {
			return new WP_Error( 'changeset_publish_unauthorized', __( 'Sorry, you are not allowed to publish customize changesets.' ), array(
				'status' => 403,
			) );
		}

		$prepared_post->post_type = $this->post_type;

		$post_id = wp_insert_post( wp_slash( (array) $prepared_post ), true );

		if ( is_wp_error( $post_id ) ) {

			if ( 'db_insert_error' === $post_id->get_error_code() ) {
				$post_id->add_data( array(
					'status' => 500,
				) );
			} else {
				$post_id->add_data( array(
					'status' => 400,
				) );
			}

			return $post_id;
		}

		$post = get_post( $post_id );

		/**
		 * Fires after a changeset post is created or updated via the REST API.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_Post         $post     Inserted or updated post object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a post, false when updating.
		 */
		do_action( "rest_insert_{$this->post_type}", $post, $request, true );

		$fields_update = $this->update_additional_fields_for_object( $post, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post->post_name ) ) );

		return $response;
	}

	/**
	 * Deletes a changeset.
	 *
	 * @since ?.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$manager = $this->ensure_customize_manager( $request['uuid'] );

		if ( ! $manager->changeset_post_id() ) {
			return new WP_Error(
				'rest_post_invalid_uuid',
				__( 'Invalid changeset UUID.' ),
				array(
					'status' => 404,
				)
			);
		}

		$post = get_post( $manager->changeset_post_id() );

		if ( $request['force'] ) {
			$previous = $this->prepare_item_for_response( $post, $request );

			// TODO: At this point $wp_customize will no longer have up-to-date post data.
			$result = wp_delete_post( $manager->changeset_post_id(), true );

			if ( ! $result ) {
				return new WP_Error(
					'rest_cannot_delete',
					__( 'The post cannot be deleted.' ),
					array(
						'status' => 500,
					)
				);
			}

			$response = new WP_REST_Response();

			$response->set_data( array(
				'deleted' => true,
				'previous' => $previous->get_data(),
			) );

			return $response;
		}

		// TODO (?): Not filterable a la WP_REST_Posts_Controller.
		if ( ! ( EMPTY_TRASH_DAYS > 0 ) ) {
			return new WP_Error(
				'rest_trash_not_supported',
				__( 'The post does not support trashing. Set force=true to delete.' ),
				array(
					'status' => 501,
				)
			);
		}

		if ( 'trash' === get_post_status( $manager->changeset_post_id() ) ) {
			return new WP_Error(
				'rest_already_trashed',
				__( 'The changeset has already been deleted.' ),
				array(
					'status' => 410,
				)
			);
		}

		_wp_customize_trash_changeset( $manager->changeset_post_id() );

		// TODO: At this point $wp_customize will no longer have up-to-date post data.
		$result = get_post( $manager->changeset_post_id() );

		if ( ! $result || 'trash' !== get_post_status( $result ) ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'The post cannot be deleted.' ),
				array(
					'status' => 500,
				)
			);
		}

		return $this->prepare_item_for_response( $result, $request );
	}

	/**
	 * Check whether a request can delete the specified changeset.
	 *
	 * @since ?.?.?
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has access to delete the item, otherwise false or WP_Error object.
	 */
	public function delete_item_permissions_check( $request ) {
		$manager = $this->ensure_customize_manager( $request['uuid'] );

		if ( ! $manager->changeset_post_id() ) {
			return new WP_Error(
				'rest_post_invalid_uuid',
				__( 'Invalid changeset UUID.' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->delete_post, $manager->changeset_post_id() ) ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'Sorry, you are not allowed to delete this item.' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares a customize changeset for create or update.
	 *
	 * @since 4.?.?
	 * @access protected
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Post object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_post = new stdClass;

		$existing_post = $this->get_customize_changeset_post( $request['uuid'] );

		$manager = $this->ensure_customize_manager( $request['uuid'] );
		$prepared_post->ID = $manager->changeset_post_id();

		if ( ! $existing_post ) {
			$prepared_post->post_name = $request['uuid'];
		}

		// Post title.
		if ( isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$prepared_post->post_title = $request['title'];
			} elseif ( ! empty( $request['title']['raw'] ) ) {
				$prepared_post->post_title = $request['title']['raw'];
			}
		}

		// Settings.
		if ( isset( $request['settings'] ) ) {
			$data = $manager->changeset_data();
			$current_user_id = get_current_user_id();

			if ( ! is_array( $request['settings'] ) ) {
				return new WP_Error( 'invalid_customize_changeset_data', __( 'Invalid customize changeset data.' ), array(
					'status' => 400,
				) );
			}
			foreach ( $request['settings'] as $setting_id => $params ) {

				$setting = $manager->get_setting( $setting_id );
				if ( ! $setting ) {
					return new WP_Error( 'invalid_customize_changeset_data', __( 'Invalid setting.' ), array(
						'status' => 400,
					) );
				}
				if ( ! $setting->check_capabilities() ) {
					return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit some of the settings.' ), array(
						'status' => 403,
					) );
				}

				if ( isset( $data[ $setting_id ] ) ) {

					// If the value of the setting is null, this should be removed from the changeset.
					if ( null === $params || 'null' === $params ) {
						unset( $data[ $setting_id ] );
						continue;
					}

					if ( isset( $params['value'] ) && ( 'null' === $params['value'] || null === $params['value'] ) ) {
						return new WP_Error( 'invalid_customize_changeset_data', __( 'Invalid setting value.' ), array(
							'status' => 400,
						) );
					}

					// Merge any additional setting params that have been supplied with the existing params.
					$merged_setting_params = array_merge( $data[ $setting_id ], $params );

					// Skip updating setting params if unchanged (ensuring the user_id is not overwritten).
					if ( $data[ $setting_id ] === $merged_setting_params ) {
						continue;
					}
				} else {
					$merged_setting_params = $params;
				}

				$data[ $setting_id ] = array_merge(
					$merged_setting_params,
					array(
						'type' => $setting->type,
						'user_id' => $current_user_id,
					)
				);
			} // End foreach().

			$prepared_post->post_content = wp_json_encode( $data );

		} // End if().

		// Date.
		if ( ! empty( $request['date'] ) ) {
			$date_data = rest_get_date_with_gmt( $request['date'] );
		} elseif ( ! empty( $request['date_gmt'] ) ) {
			$date_data = rest_get_date_with_gmt( $request['date_gmt'], true );
		}

		if ( isset( $date_data ) ) {
			list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
			$prepared_post->edit_date = true;
		}

		// Author.
		if ( ! empty( $request['author'] ) ) {
			$post_author = (int) $request['author'];

			if ( get_current_user_id() !== $post_author ) {
				$user_obj = get_userdata( $post_author );

				if ( ! $user_obj ) {
					return new WP_Error( 'rest_invalid_author', __( 'Invalid author ID.' ), array(
						'status' => 400,
					) );
				}
			}

			$prepared_post->post_author = $post_author;
		}

		// Status.
		if ( isset( $request['status'] ) ) {

			$status_check = $this->sanitize_post_statuses( $request['status'], $request, $this->post_type );
			if ( is_wp_error( $status_check ) ) {
				return $status_check;
			} else {
				if ( is_array( $request['status'] ) ) {
					$status = $request['status'][0];
				} else {
					$status = $request['status'];
				}
				$prepared_post->post_status = $status;
			}

			if ( 'publish' === $prepared_post->post_status ) {

				// Change date to current date if publishing.
				$date_data = rest_get_date_with_gmt( date( 'Y-m-d H:i:s', time() ), true );
				list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
				$prepared_post->edit_date = true;
			} elseif ( 'future' === $prepared_post->post_status ) {
				if ( property_exists( $prepared_post, 'post_date' ) ) {
					$date = $prepared_post->post_date;
				} else {
					$date = $existing_post->post_date;
				}

				if ( $date <= get_gmt_from_date( date( 'Y-m-d H:i:s', time() ) ) ) {
					return new WP_Error( 'rest_invalid_param', __( 'Incorrect date, date cannot be in past for future post.' ), array(
						'status' => 402,
					) );
				}
			}
		} elseif ( ! $existing_post ) {
			$prepared_post->post_status = 'auto-draft';
		} // End if().

		// Setting a date for auto-draft is forbidden.
		if ( isset( $date_data ) && $existing_post ) {
			if (
				(
					property_exists( $prepared_post, 'post_status' )
					&&
					'auto-draft' === $prepared_post->post_status
				)
				||
				(
					! property_exists( $prepared_post, 'post_status' )
					&&
					'auto-draft' === $existing_post->post_status
				)
			) {
				return new WP_Error( 'rest_invalid_param', __( 'Sorry, cannot supply date for auto-draft changeset.' ), array(
					'status' => 402,
				) );
			}
		}

		/**
		 * Filters a changeset post before it is inserted via the REST API.
		 *
		 * @since 4.?.?
		 *
		 * @param stdClass        $prepared_post An object representing a single post prepared
		 *                                       for inserting or updating the database.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( "rest_pre_insert_{$this->post_type}", $prepared_post, $request );

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
				'uuid' => 'post_name',
				'title' => 'post_title',
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
			'description'       => __( 'Limit result set to posts assigned one or more statuses.' ),
			'type'              => 'array',
			'items'             => array(
				'enum'          => array_merge( $this->statuses, array( 'any' ) ),
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
				'uuid',
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

		$manager = $this->ensure_customize_manager( $changeset_post->post_name );

		$data = array();

		$data['date'] = $this->prepare_date_response( $changeset_post->post_date_gmt, $changeset_post->post_date );
		if ( '0000-00-00 00:00:00' === $changeset_post->post_date_gmt ) {
			$post_date_gmt = get_gmt_from_date( $changeset_post->post_date );
		} else {
			$post_date_gmt = $changeset_post->post_date_gmt;
		}
		$data['date_gmt'] = $this->prepare_date_response( $post_date_gmt );

		$data['uuid'] = $changeset_post->post_name;
		$data['status'] = $changeset_post->post_status;

		$data['title'] = array(
			'raw'      => $changeset_post->post_title,
			'rendered' => get_the_title( $changeset_post->ID ),
		);

		$raw_settings = json_decode( $changeset_post->post_content, true );

		$settings = array();
		if ( is_array( $raw_settings ) ) {
			foreach ( $raw_settings as $setting_id => $params ) {

				$setting = $manager->get_setting( $setting_id );
				if ( ! $setting || ! $setting->check_capabilities() ) {
					continue;
				}
				$settings[ $setting_id ] = $params;
			}
		}

		$data['settings'] = $settings;

		$data['author'] = (int) $changeset_post->post_author;

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		/**
		 * Filters the customize changeset data for a response.
		 *
		 * @since 4.?.?
		 *
		 * @param WP_REST_Response $response       The response object.
		 * @param WP_Post          $changeset_post Customize Changeset Post object.
		 * @param WP_REST_Request  $request        Request object.
		 */
		return apply_filters( 'rest_prepare_customize_changeset', $response, $changeset_post, $request );
	}

	/**
	 * Checks the post_date_gmt or and prepare for single post output.
	 *
	 * @since 4.?.?
	 * @access protected
	 *
	 * @param string      $date_gmt GMT publication time.
	 * @param string|null $date     Optional. Local publication time. Default null.
	 * @return string|null Formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {

		// Use the date if passed.
		if ( isset( $date ) ) {
			if ( $this->is_valid_date( $date ) ) {
				return $date;
			} else {
				return date( 'Y-m-d H:i:s', time() );
			}
		}

		// Return null if $date_gmt is empty/zeros.
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		if ( $this->is_valid_date( $date_gmt ) ) {
			return $date_gmt;
		} else {
			return date( 'Y-m-d H:i:s', time() );
		}
	}

	/**
	 * Checks if date is valid.
	 *
	 * @param string $date Date string.
	 * @return bool|DateTime|string Return DateTime | if succeeds.
	 */
	protected function is_valid_date( $date ) {
		if ( method_exists( 'DateTime', 'createFromFormat' ) ) {
			return DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
		} else {

			// For 5 >= 5.2.0.
			$date = new DateTime( $date );
			return $date->format( 'Y-m-d H:i:s' );
		}
	}

	/**
	 * Get customize changeset post object.
	 *
	 * @param string $uuid Changeset UUID.
	 * @return WP_Post|null Post object.
	 */
	protected function get_customize_changeset_post( $uuid ) {
		$settings_previewed = true;
		$customize_manager = new WP_Customize_Manager( compact( 'settings_previewed' ) );
		$post = get_post( $customize_manager->find_changeset_post_id( $uuid ) );

		return $post;
	}

	/**
	 * Check if customize changeset is already published.
	 *
	 * @param WP_Post $changeset WP_Post object.
	 * @return bool If the customize changeset is already published.
	 */
	protected function is_published_changeset( $changeset ) {
		$is_published = (
			'trash' === $changeset->post_status
			||
			'publish' === $changeset->post_status
		);
		return $is_published;
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

	/**
	 * Sanitizes and validates the status.
	 *
	 * @since 4.?.?
	 * @access public
	 *
	 * @param  string $status Post status.
	 * @return string|WP_Error Valid status, otherwise WP_Error object.
	 */
	public function sanitize_post_status( $status ) {

		$status = sanitize_text_field( $status );
		if ( in_array( $status, $this->statuses, true ) ) {
			return $status;
		}
		return new WP_Error( 'rest_incorrect_status', __( 'Incorrect status format.' ), array(
			'status' => rest_authorization_required_code(),
		) );
	}

	/**
	 * Make sure the datetime is in correct format.
	 *
	 * @param string $date Date string.
	 * @return string|WP_Error Date string or error.
	 */
	public function sanitize_datetime( $date ) {
		if ( $this->is_valid_date( $date ) ) {
			if ( $date < get_gmt_from_date( date( 'Y-m-d H:i:s', time() ) ) ) {
				return new WP_Error( 'rest_incorrect_date', __( 'Incorrect date, date cannot be in past.' ), array(
					'status' => 402,
				) );
			}
			return $date;
		} else {
			return new WP_Error( 'rest_incorrect_date', __( 'Incorrect date format' ), array(
				'status' => 402,
			) );
		}
	}

	/**
	 * Sanitize UUID.
	 *
	 * @param string $uuid UUID.
	 * @return string|WP_Error Sanitized string / WP_Error if wrong format.
	 */
	public function sanitize_uuid( $uuid ) {
		if ( ! preg_match( '/^' . self::REGEX_CHANGESET_UUID . '$/', $uuid ) ) {
			return new WP_Error( 'rest_incorrect_uuid', __( 'Incorrect UUID.' ), array(
				'status' => 402,
			) );
		}

		return $uuid;
	}
}
