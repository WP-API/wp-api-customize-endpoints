<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Changesets_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * Class WP_Test_REST_Customize_Changesets_Controller.
 *
 * @group restapi
 */
class WP_Test_REST_Customize_Changesets_Controller extends WP_Test_REST_Controller_TestCase {

	/**
	 * REST Server.
	 *
	 * Note that this variable is already defined on the parent class but it lacks the phpdoc variable type.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Subscriber user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Admin user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		}

		// Deleted by _delete_all_data() in WP_UnitTestCase::tearDownAfterClass().
		self::$subscriber_id = $factory->user->create( array(
			'role' => 'subscriber',
		) );

		self::$admin_id = $factory->user->create( array(
			'role' => 'administrator',
		) );

		self::$editor_id = $factory->user->create( array(
			'role' => 'editor',
		) );

		$editor = new WP_User( self::$editor_id );
		$editor->add_cap( 'customize' );
	}

	/**
	 * Setup.
	 */
	public function setUp() {
		parent::setUp();
		add_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );
	}

	/**
	 * Return a WP_Error with an 'illegal' code..
	 *
	 * @return WP_Error
	 */
	public function __return_error_illegal() {
		return new WP_Error( 'illegal' );
	}

	const ALLOWED_TEST_SETTING_ID = 'allowed_setting';

	const FORBIDDEN_TEST_SETTING_ID = 'forbidden_setting';

	/**
	 * Add custom settings for testing.
	 *
	 * @param WP_Customize_Manager $wp_customize WP Customize Manager.
	 */
	public function add_test_customize_settings( $wp_customize ) {
		$wp_customize->add_setting( self::ALLOWED_TEST_SETTING_ID );
		$wp_customize->add_setting( self::FORBIDDEN_TEST_SETTING_ID, array(
			'capability' => 'do_not_allow',
		) );
		$wp_customize->add_setting( 'foo' );
		$wp_customize->add_setting( 'basic_option' );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/customize/v1/changesets', $routes );
		$this->assertArrayHasKey( '/customize/v1/changesets/(?P<uuid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_context_param()
	 */
	public function test_context_param() {

		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/changesets' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/changesets/' . $manager->changeset_uuid() );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {

		$changeset_controller = new WP_REST_Customize_Changesets_Controller();
		$schema = $changeset_controller->get_item_schema();
		$properties = $schema['properties'];

		$this->assertEquals( 7, count( $properties ) );

		$this->assertArrayHasKey( 'author', $properties );
		$this->assertSame( 'integer', $properties['author']['type'] );

		$this->assertArrayHasKey( 'date', $properties );
		$this->assertSame( 'string', $properties['date']['type'] );
		$this->assertArrayHasKey( 'sanitize_callback', $properties['date']['arg_options'] );

		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertSame( 'string', $properties['date_gmt']['type'] );
		$this->assertArrayHasKey( 'sanitize_callback', $properties['date_gmt']['arg_options'] );

		$this->assertArrayHasKey( 'settings', $properties ); // Instead of content.
		$this->assertSame( 'object', $properties['settings']['type'] );

		$this->assertArrayHasKey( 'uuid', $properties );
		$this->assertSame( 'string', $properties['uuid']['type'] );
		$this->assertArrayHasKey( 'sanitize_callback', $properties['uuid']['arg_options'] );

		$this->assertArrayHasKey( 'status', $properties );
		$this->assertSame( 'string', $properties['status']['type'] );
		$this->assertArrayHasKey( 'sanitize_callback', $properties['status']['arg_options'] );

		$this->assertArrayHasKey( 'title', $properties );
		$this->assertSame( 'object', $properties['title']['type'] );
		$this->assertArrayHasKey( 'sanitize_callback', $properties['title']['arg_options'] );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $manager->changeset_uuid(), $data['uuid'] );
	}

	/**
	 * Test getting changeset without having proper permissions.
	 */
	public function test_get_item_without_permissions() {
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test getting changeset with invalid UUID.
	 */
	public function test_get_item_with_invalid_uuid() {

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets/%s', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * Test getting changeset list with edit context with proper permissions.
	 */
	public function test_get_item_list_context_with_permission() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/customize/v1/changesets' );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test getting changeset list with edit context without proper permissions.
	 */
	public function test_get_item_list_context_without_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', '/customize/v1/changesets' );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * Test getting a changeset with edit context without proper permissions.
	 */
	public function test_get_item_context_without_permission() {
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_items()
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets' ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 2, count( $response->get_data() ) );
	}

	/**
	 * Test getting items by author.
	 */
	public function test_get_items_by_author() {
		wp_set_current_user( self::$admin_id );
		$user_1_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );
		$user_2_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );

		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
			'post_title' => 'Title 1',
			'post_author' => $user_1_id,
		) );
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
			'post_title' => 'Title 2',
			'post_author' => $user_2_id,
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets' ) );
		$request->set_query_params( array(
			'author' => $user_1_id,
		) );

		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = rest_ensure_response( $response );

		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertSame( 'Title 1', $data[0]['title']['rendered'] );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets' ) );
		$request->set_query_params( array(
			'author_exclude' => $user_1_id,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = rest_ensure_response( $response );

		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertSame( 'Title 2', $data[0]['title']['rendered'] );

	}

	/**
	 * Test getting item by status.
	 */
	public function test_get_item_by_status() {
		wp_set_current_user( self::$admin_id );

		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'draft',
			'post_content' => '{}',
		) );
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets' ) );
		$request->set_query_params( array(
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = rest_ensure_response( $response );

		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertSame( 'draft', $data[0]['status'] );
	}

	/**
	 * Test getting items with per_page param.
	 */
	public function test_get_items_by_per_page() {
		wp_set_current_user( self::$admin_id );

		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets' ) );
		$request->set_query_params( array(
			'per_page' => '1',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = rest_ensure_response( $response );

		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
	}

	/**
	 * Filter query args.
	 *
	 * @param array $args Query args.
	 * @return array Args.
	 */
	public function filter_rest_customize_changeset_query( $args ) {
		$args['post_status'] = array( 'draft' );
		return $args;
	}

	/**
	 * Test get items with filter_rest_customize_changeset_query filter.
	 */
	public function test_get_items_with_filter() {
		wp_set_current_user( self::$admin_id );

		$draft_id = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $draft_id,
			'post_status' => 'draft',
			'post_content' => '{}',
		) );
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => wp_generate_uuid4(),
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );

		add_filter( 'rest_customize_changeset_query', array( $this, 'filter_rest_customize_changeset_query' ), 10, 1 );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets' ) );
		$request->set_query_params( array(
			'status' => 'auto-draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = rest_ensure_response( $response );

		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertSame( $draft_id, $data[0]['uuid'] );

		remove_filter( 'rest_customize_changeset_query', array( $this, 'filter_rest_customize_changeset_query' ) );
	}

	/**
	 * Test the case when user doesn't have permissions for some of the settings.
	 */
	public function test_get_item_without_permissions_to_some_settings() {
		wp_set_current_user( self::$admin_id );

		$settings = wp_json_encode( array(
			self::ALLOWED_TEST_SETTING_ID => array(
				'value' => 'Foo',
			),
			self::FORBIDDEN_TEST_SETTING_ID => array(
				'value' => 'Bar',
			),
		) );
		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'auto-draft',
			'post_content' => $settings,
		) );

		add_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets/%s', $uuid ) );

		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$changeset_data = $response->get_data();
		$changeset_settings = $changeset_data['settings'];

		$this->assertArrayHasKey( self::ALLOWED_TEST_SETTING_ID, $changeset_settings );
		$this->assertFalse( isset( $changeset_settings[ self::FORBIDDEN_TEST_SETTING_ID ] ) );

		remove_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );
	}

	/**
	 * Filter for GET request.
	 *
	 * @param WP_REST_Response $response Response data.
	 * @return array Filtered data.
	 */
	public function get_changeset_custom_callback( $response ) {
		$response->set_data( array(
			'settings' => array(
				'foo' => array(
					'value' => 'bar',
				),
			),
		) );

		return $response;
	}

	/**
	 * Test getting item with filter applied.
	 */
	public function test_get_item_with_filter() {
		wp_set_current_user( self::$admin_id );

		add_filter( 'rest_prepare_customize_changeset', array( $this, 'get_changeset_custom_callback' ), 10, 1 );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );
		$changeset_data = $response->get_data();

		$changeset_settings = $changeset_data['settings'];

		$this->assertArrayHasKey( 'foo', $changeset_settings );
		$this->assertSame( $changeset_settings['foo']['value'], 'bar' );

		remove_all_filters( 'rest_pre_get_changeset' );
	}

	/**
	 * Test create_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::create_item()
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		add_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'settings' => array(
				self::ALLOWED_TEST_SETTING_ID => array(
					'value' => 'bar',
				),
			),
			'title' => 'Title',
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 'Title', $data['title']['raw'] );

		$changeset_settings = $data['settings'];
		$this->assertSame( 'bar', $changeset_settings[ self::ALLOWED_TEST_SETTING_ID ]['value'] );
	}

	/**
	 * Tests that user without appropriate permissions can't create a changeset as another user.
	 */
	public function test_create_item_as_another_user_without_permissions() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'author' => 1,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_others', $response, 403 );
	}

	/**
	 * Test tha creating changeset with invalid data fails.
	 */
	public function test_create_item_invalid_data() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'settings' => '[MALFORMED]',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response, 400 );
	}

	/**
	 * Test that subscriber can't create a changeset.
	 */
	public function test_create_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'title' => 'Title',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	/**
	 * Test creating an item with 'publish' status. This should publish the changeset settings.
	 */
	public function test_create_item_already_published() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'status' => 'publish',
			'settings' => array(
				'blogname' => array(
					'value' => 'Blogname',
				),
			),
		) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'trash', $data['status'] );

		$this->assertEquals( get_option( 'blogname' ), 'Blogname' );
	}

	/**
	 * Test that changeset can't be created with invalid status.
	 *
	 * @dataProvider data_bad_customize_changeset_status
	 * @param string $bad_status Bad status.
	 */
	public function test_create_item_invalid_status( $bad_status ) {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_param( 'status', $bad_status );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test creating a changeset without having permissions to the changed settings.
	 */
	public function test_create_item_without_settings_permissions() {
		wp_set_current_user( self::$editor_id );
		$user = new WP_User( self::$editor_id );
		$user->remove_cap( 'edit_theme_options' );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'settings' => array(
				'title' => array(
					'value' => 'Title',
				),
			),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
		$user->remove_cap( 'edit_theme_options' );
	}

	/**
	 * Test that create_item() rejects creating changesets with nonexistant and disallowed post statuses.
	 *
	 * @dataProvider data_bad_customize_changeset_status
	 *
	 * @param string $bad_status Bad status.
	 */
	public function test_create_item_bad_customize_changeset_status( $bad_status ) {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'status' => $bad_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test that creating a customize changeset with non-existent post ID-s fails.
	 */
	public function test_create_item_with_invalid_setting_values() {
		wp_set_current_user( self::$admin_id );
		$menu_id = wp_create_nav_menu( 'menu' );
		$menu_item_id = wp_update_nav_menu_item( $menu_id, 0 );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );

		$args = array(
			'menu-item-object-id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			'menu-item-object' => 'post',
		);
		$params = array(
			'settings' => array(
				'nav_menu_item[' . $menu_item_id . ']' => array(
					'value' => $args,
					'type' => 'nav_menu_item',
				),
			),
		);
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response, 400 );
	}



	/**
	 * Test that create_item() can create a changeset with a passed date.
	 */
	public function test_create_item_with_date() {
		wp_set_current_user( self::$admin_id );

		$date_gmt = date( 'Y-m-d H:i:s', ( time() + YEAR_IN_SECONDS ) );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'date_gmt' => $date_gmt,
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( get_date_from_gmt( $data['date_gmt'] ), get_date_from_gmt( $date_gmt ) );
	}

	/**
	 * Test that creating a published changeset with update_item() sets the changeset date to now.
	 */
	public function test_create_item_published_changeset_resets_date() {
		wp_set_current_user( self::$admin_id );

		$this_year = date( 'Y' );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'date_gmt' => date( 'Y-m-d H:i:s', ( time() + YEAR_IN_SECONDS ) ),
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $this_year, date( 'Y', strtotime( $data['date_gmt'] ) ) );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $data['uuid'],
		) );

		$post_id = $manager->changeset_post_id();
		$this->assertInternalType( 'int', $post_id );
		$changeset_post = get_post( $post_id );
		$this->assertSame( $this_year, date( 'Y', strtotime( $changeset_post->post_date_gmt ) ) );
	}

	/**
	 * Test that create_item() rejects creating a changeset with a past date.
	 */
	public function test_create_item_with_past_date() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'date_gmt' => strtotime( '-1 week' ),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response );
	}

	/**
	 * Test that create_item() rejects creating a changeset with an invalid date.
	 */
	public function test_create_item_bad_customize_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'date_gmt' => 'BAD DATE',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test that create_item() updates an item when UUID is provided and the changeset exists.
	 */
	public function test_create_item_update_item() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'title' => 'Title',
			'settings' => '{}',
		) );

		$request = new WP_REST_Request( 'POST', '/customize/v1/changesets' );
		$request->set_body_params( array(
			'uuid' => $manager->changeset_uuid(),
			'settings' => array(
				'blogname' => array(
					'value' => 'Foo',
				),
			),
		) );

		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$changeset_data = $response->get_data();
		$changeset_settings = $changeset_data['settings'];

		$this->assertSame( 'Foo', $changeset_settings['blogname']['value'] );
	}

	/**
	 * Test update_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::update_item()
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'title' => 'Title',
			'settings' => '{}',
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'settings' => array(
				'blogname' => array(
					'value' => 'Foo',
				),
			),
		) );

		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$changeset_data = $response->get_data();
		$changeset_settings = $changeset_data['settings'];

		$this->assertSame( 'Foo', $changeset_settings['blogname']['value'] );
	}

	/**
	 * Filters updating changeset.
	 *
	 * @see WP_REST_Customize_Changesets_Controller::prepare_item_for_database()
	 * @param stdClass $changeset_post Object containing prepared post data.
	 * @return stdClass Post.
	 */
	public function update_changeset_custom_callback( $changeset_post ) {
		$changeset_post->post_title = 'Title after';
		return $changeset_post;
	}

	/**
	 * Test updating item with filter applied.
	 */
	public function test_update_item_with_filter() {
		wp_set_current_user( self::$admin_id );

		$title_before = 'Title before';
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'title' => $title_before,
		) );

		add_filter( 'rest_pre_insert_customize_changeset', array( $this, 'update_changeset_custom_callback' ), 10, 1 );

		$title_after = 'Title after';
		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_param( 'title', $title_after );
		$request->set_param( 'status', 'draft' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $title_after, $data['title']['raw'] );
		$this->assertEquals( 'draft', $data['status'] );

		remove_all_filters( 'rest_pre_insert_customize_changeset' );
	}

	/**
	 * Test the case when the user doesn't have permissions to edit some of the settings within the changeset.
	 */
	public function test_update_item_cannot_edit_some_settings() {

		wp_set_current_user( self::$admin_id );

		$settings = wp_json_encode( array(
			self::ALLOWED_TEST_SETTING_ID => array(
				'value' => 'Foo',
			),
			self::FORBIDDEN_TEST_SETTING_ID => array(
				'value' => 'Bar',
			),
		) );
		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'auto-draft',
			'post_content' => $settings,
		) );

		add_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );

		$changed_value = 'changed_setting_value';
		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				self::FORBIDDEN_TEST_SETTING_ID => array(
					'value' => $changed_value,
				),
				self::ALLOWED_TEST_SETTING_ID => array(
					'value' => $changed_value,
				),
			),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				self::ALLOWED_TEST_SETTING_ID => array(
					'value' => $changed_value,
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$changeset_data = $response->get_data();
		$changeset_settings = $changeset_data['settings'];

		$this->assertSame( $changed_value, $changeset_settings[ self::ALLOWED_TEST_SETTING_ID ]['value'] );

		remove_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );
	}

	/**
	 * Test that slug of a new changeset cannot be changed with update_item().
	 */
	public function test_update_item_cannot_create_changeset_slug() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$bad_slug = 'slug-after';

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'uuid' => $bad_slug,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_incorrect_uuid', $response, 402 );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( get_post( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test that slug of an existing changeset cannot be changed with update_item().
	 */
	public function test_update_item_cannot_edit_changeset_slug() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$bad_slug = 'slug-after';

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'uuid' => $bad_slug,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_incorrect_uuid', $response, 402 );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_name, $manager->changeset_uuid() );
	}

	/**
	 * Test that changesets cannot be created with update_item() when the user lacks capabilities.
	 */
	public function test_update_item_cannot_create_changeset_post() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that changesets cannot be updated with update_item() when the user lacks capabilities.
	 */
	public function test_update_item_cannot_edit_changeset_post() {
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$changeset_post_id = $manager->changeset_post_id();

		$content_before = get_post( $changeset_post_id )->post_content;

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'settings' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );

		$content_after = get_post( $changeset_post_id )->post_content;

		// For PHP 5.2.
		if ( method_exists( $this, 'assertJsonStringEqualsJsonString' ) ) {
			$this->assertJsonStringEqualsJsonString( $content_before, $content_after );
		} else {
			$this->assertEquals( $content_before, $content_after );
		}
	}

	/**
	 * Test that update_item() rejects invalid changeset data.
	 */
	public function test_update_item_invalid_changeset_data() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'basic_option' => array(
				'value' => 'Foo',
			),
		) );
		$changeset_post_id = $manager->changeset_post_id();

		$content_before = get_post( $changeset_post_id )->post_content;

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'settings' => '[MALFORMED]',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response, 400 );

		$content_after = get_post( $changeset_post_id )->post_content;

		// For PHP 5.2.
		if ( method_exists( $this, 'assertJsonStringEqualsJsonString' ) ) {
			$this->assertJsonStringEqualsJsonString( $content_before, $content_after );
		} else {
			$this->assertEquals( $content_before, $content_after );
		}
	}

	/**
	 * Test that update_item() can create a changeset with a title.
	 */
	public function test_update_item_create_changeset_title() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$title = 'FooBarBaz';

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'title' => $title,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $title, $data['title']['raw'] );

		$manager = new WP_Customize_Manager();
		$this->assertSame( get_post( $manager->find_changeset_post_id( $uuid ) )->post_title, $title );
	}

	/**
	 * Test that changeset titles can be updated with update_item().
	 */
	public function test_update_item_edit_changeset_title() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$title_before = 'Foo';
		$title_after = 'Bar';

		$manager->save_changeset_post( array(
			'title' => $title_before,
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'title' => $title_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $title_after, $data['title']['raw'] );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_title, $title_after );
	}

	/**
	 * Test that update_item() rejects updating a changeset with nonexistant and disallowed post statuses.
	 *
	 * @dataProvider data_bad_customize_changeset_status
	 *
	 * @param string $bad_status Bad status.
	 */
	public function test_update_item_edit_bad_customize_changeset_status( $bad_status ) {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $bad_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * Bad changeset statuses.
	 */
	public function data_bad_customize_changeset_status() {
		return array(
			// Probably doesn't exist.
			array( '437284923487239847293487' ),
			// Not in the whitelist.
			array( 'trash' ),
		);
	}

	/**
	 * Test that update_item() does not create a published changeset if the user lacks capabilities.
	 *
	 * @dataProvider data_publish_changeset_status
	 *
	 * @param array $publish_status Status to publish the changeset.
	 */
	public function test_update_item_create_changeset_publish_unauthorized( $publish_status ) {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => $publish_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );

		$manager = new WP_Customize_Manager();
		$this->assertFalse( get_post_status( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test that update_item() rejects publishing changesets if the user lacks capabilities.
	 *
	 * @dataProvider data_publish_changeset_status
	 *
	 * @param array $publish_status Status to publish the changeset.
	 */
	public function test_update_item_changeset_publish_unauthorized( $publish_status ) {
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $publish_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * "Publish (verb) changeset" statuses.
	 */
	public function data_publish_changeset_status() {
		return array(
			array( 'publish' ),
			array( 'future' ),
		);
	}

	/**
	 * Test that update_item() rejects updating a published changeset.
	 *
	 * @dataProvider data_published_changeset_status
	 *
	 * @param string $published_status Published status.
	 */
	public function test_update_item_changeset_already_published( $published_status ) {
		wp_set_current_user( self::$admin_id );

		$changeset_data_before = array(
			self::ALLOWED_TEST_SETTING_ID => array(
				'value' => 'Foo',
			),
		);
		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => $published_status,
			'post_content' => wp_json_encode( $changeset_data_before ),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'basic_option' => array(
					'value' => 'Bar',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );
		$data = $manager->changeset_data();
		$this->assertFalse( isset( $data['basic_option'] ) );
	}

	/**
	 * "Published" (noun) changeset statuses.
	 */
	public function data_published_changeset_status() {
		return array(
			array( 'publish' ),
			array( 'trash' ),
		);
	}

	/**
	 * Test that update_item() can create a changeset with a passed date.
	 */
	public function test_update_item_create_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$date_gmt = date( 'Y-m-d H:i:s', ( time() + YEAR_IN_SECONDS ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'date_gmt' => $date_gmt,
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['date_gmt'], $date_gmt );

		$manager = new WP_Customize_Manager();
		$this->assertSame( get_post( $manager->find_changeset_post_id( $uuid ) )->post_date_gmt, $date_gmt );
	}

	/**
	 * Test that update_item() can edit a changeset with a passed date.
	 */
	public function test_update_item_edit_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_after = date( 'Y-m-d H:i:s', ( time() + YEAR_IN_SECONDS ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => $date_after,
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['date_gmt'], $date_after );
	}

	/**
	 * Test that a update_item() can change a 'future' changeset to 'draft', keeping the date.
	 */
	public function test_update_item_future_to_draft_keeps_post_date() {
		wp_set_current_user( self::$admin_id );

		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 year' ) );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => $future_date,
			'status' => 'future',
		) );

		$status_after = 'draft';

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $status_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $status_after, get_post_status( $manager->changeset_post_id() ) );
		$this->assertSame( $future_date, get_post( $manager->changeset_post_id() )->post_date );
	}

	/**
	 * Test that update_item() can schedule a changeset if it already has a date in the future.
	 */
	public function test_update_item_schedule_with_existing_future_date() {
		wp_set_current_user( self::$admin_id );

		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 year' ) );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => $future_date,
			'status' => 'draft',
		) );

		$status_after = 'future';

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $status_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $status_after, get_post_status( $manager->changeset_post_id() ) );
		$this->assertSame( $future_date, get_post( $manager->changeset_post_id() )->post_date );
	}

	/**
	 * Test that creating a published changeset with update_item() sets the changeset date to now.
	 */
	public function test_update_item_create_published_changeset_resets_date() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$this_year = date( 'Y' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'date_gmt' => date( 'Y-m-d H:i:s', ( time() + YEAR_IN_SECONDS ) ),
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $this_year, date( 'Y', strtotime( $data['date_gmt'] ) ) );

		$manager = new WP_Customize_Manager();
		$post_id = $manager->find_changeset_post_id( $uuid );
		$this->assertInternalType( 'int', $post_id );
		$changeset_post = get_post( $post_id );
		$this->assertSame( $this_year, date( 'Y', strtotime( $changeset_post->post_date_gmt ) ) );
	}

	/**
	 * Test that publishing a future-dated changeset with update_item() resets the changeset date to now.
	 */
	public function test_update_item_publish_existing_changeset_resets_date() {
		wp_set_current_user( self::$admin_id );

		$this_year = date( 'Y' );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => date( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			'status' => 'future',
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $this_year, date( 'Y', strtotime( $data['date_gmt'] ) ) );
		$changeset_post = get_post( $manager->changeset_post_id() );
		$this->assertSame( $this_year, date( 'Y', strtotime( $changeset_post->post_date_gmt ) ) );
	}

	/**
	 * Test that update_item() rejects creating a changeset with a past date.
	 */
	public function test_update_item_create_not_future_date_with_past_date() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'date_gmt' => date( 'Y-m-d H:i:s', strtotime( '-1 week' ) ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response );

		$manager = new WP_Customize_Manager();
		$this->assertNull( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that update_item() rejects editing a changeset with a date in the past.
	 */
	public function test_update_item_edit_not_future_date_with_past_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => date( 'Y-m-d H:i:s', strtotime( '-1 week' ) ),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response );
		$this->assertSame( $date_before, get_post( $manager->changeset_post_id() )->post_date_gmt );
	}

	/**
	 * Test that update_item() rejects scheduling a changeset when it has a past date.
	 */
	public function test_update_item_not_future_date_with_future_status() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => 'future',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response );
	}

	/**
	 * Test that update_item() rejects editing an auto-draft changeset with a date.
	 */
	public function test_update_item_edit_cannot_supply_date_for_auto_draft_changeset() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$status_before = get_post_status( $manager->changeset_post_id() );
		$this->assertSame( 'auto-draft', $status_before );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => date( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * Test that update_item() rejects creating a changeset with an invalid date.
	 */
	public function test_update_item_create_bad_customize_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'date_gmt' => 'BAD DATE',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$manager = new WP_Customize_Manager();
		$this->assertNull( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that update_item() rejects editing a changeset with an invalid date.
	 */
	public function test_update_item_edit_bad_customize_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => 'BAD DATE',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertSame( $date_before, get_post( $manager->changeset_post_id() )->post_date_gmt );
	}

	/**
	 * Tests that creating a published changeset with update_item() returns a 'publish' status.
	 */
	public function test_update_item_create_status_is_trash_after_publish() {
		if ( post_type_supports( 'customize_changeset', 'revisions' ) ) {
			$this->markTestSkipped( 'Changesets are not trashed when revisions are enabled.' );
		}

		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'trash', $data['status'] );
	}

	/**
	 * Test that publishing a changeset with update_item() returns a 'publish' status.
	 */
	public function test_update_item_edit_status_is_publish_after_publish() {
		if ( post_type_supports( 'customize_changeset', 'revisions' ) ) {
			$this->markTestSkipped( 'Changesets are not trashed when revisions are enabled.' );
		}

		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'trash', $data['status'] );
	}

	/**
	 * Test that creating a published changeset with update_item() updates a valid setting.
	 */
	public function test_update_item_create_with_bloginfo() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$blogname_after = 'test_update_item_create_with_bloginfo';

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'blogname' => array(
					'value' => $blogname_after,
				),
			),
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $blogname_after, get_option( 'blogname' ) );
	}

	/**
	 * Test that publishing a changeset with update_item() updates a valid setting.
	 */
	public function test_update_item_edit_with_bloginfo() {
		wp_set_current_user( self::$admin_id );

		$blogname_after = 'test_update_item_edit_with_bloginfo';

		$changeset_data = array(
			'blogname' => array(
				'value' => $blogname_after,
			),
		);

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'draft',
			'post_content' => wp_json_encode( $changeset_data ),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $blogname_after, get_option( 'blogname' ) );
	}

	/**
	 * Test that update_item() returns setting validities.
	 */
	public function test_update_item_setting_validities() {
		wp_set_current_user( self::$admin_id );

		$bad_setting = 'test_update_item_setting_validities';
		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				$bad_setting => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that using update_item() to transactionally insert a changeset fails when settings are invalid.
	 */
	public function test_update_item_insert_transaction_fail_setting_validities() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$illegal_setting = 'foo_illegal';
		add_filter( "customize_validate_{$illegal_setting}", array( $this, '__return_error_illegal' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'blogname' => array(
					'value' => 'test_update_item_insert_transaction_fail_setting_validities',
				),
				$illegal_setting => array(
					'value' => 'Foo',
				),
			),
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that using update_item() to transactionally update a changeset fails when settings are invalid.
	 */
	public function test_update_item_update_transaction_fail_setting_validities() {
		wp_set_current_user( self::$admin_id );

		$blogname_before = 'test_update_item_update_transaction_fail_setting_validities';

		$changeset_data = array(
			'blogname' => array(
				'value' => $blogname_before,
			),
		);

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'draft',
			'post_content' => wp_json_encode( $changeset_data ),
		) );

		$illegal_setting = 'foo_illegal';
		add_filter( "customize_validate_{$illegal_setting}", array( $this, '__return_error_illegal' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'blogname' => array(
					'value' => $blogname_before . '_updated',
				),
				$illegal_setting => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );
		$data = $manager->changeset_data();
		$this->assertSame( $blogname_before, $data['blogname']['value'] );
	}

	/**
	 * Test that update_item() reports errors inserting a changeset.
	 */
	public function test_update_item_insert_changeset_post_save_failure() {
		wp_set_current_user( self::$admin_id );

		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'empty_content', $response );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( $manager->find_changeset_post_id( $uuid ) );

	}

	/**
	 * Test that update_item() reports errors updating a changeset.
	 */
	public function test_update_item_update_changeset_post_save_failure() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'auto-draft',
			'post_content' => '{}',
		) );

		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$params = array(
			'date_gmt' => date( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
			'status' => 'draft',
		);
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'empty_content', $response );

		$manager = new WP_Customize_Manager();
		$post = get_post( $manager->find_changeset_post_id( $uuid ) );
		$this->assertNotSame( get_post_status( $post ), $params['status'] );
		$this->assertLessThan( $params['date_gmt'], $post->post_date_gmt );
	}

	/**
	 * Test that a customize changeset's status cannot be updated to auto-draft.
	 */
	public function test_update_item_auto_draft_forbidden() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'draft',
			'post_content' => '{}',
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );

		$request->set_param( 'status', 'auto-draft' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );
		$post = get_post( $manager->changeset_post_id() );
		$this->assertEquals( 'draft', $post->post_status );
	}

	/**
	 * Test removing a setting from customize changeset.
	 */
	public function test_update_item_remove_setting() {
		wp_set_current_user( self::$admin_id );

		$changeset_data = array(
			'blogname' => array(
				'value' => 'Foo',
			),
			self::ALLOWED_TEST_SETTING_ID => array(
				'value' => 'Bar',
			),
		);

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'draft',
			'post_content' => wp_json_encode( $changeset_data ),
		) );

		add_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'blogname' => null,
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );
		$data = $manager->changeset_data();
		$this->assertTrue( ! isset( $data['blogname'] ) );
		$this->assertTrue( isset( $data[ self::ALLOWED_TEST_SETTING_ID ] ) );

		remove_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );
	}

	/**
	 * Test that it's possible to set custom setting params.
	 */
	public function test_update_item_custom_params() {
		wp_set_current_user( self::$admin_id );

		$changeset_data = array(
			'blogname' => array(
				'value' => 'Foo',
			),
		);

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'draft',
			'post_content' => wp_json_encode( $changeset_data ),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'settings' => array(
				'blogname' => array(
					'custom' => 'Bar',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );
		$data = $manager->changeset_data();

		$expected_params = array(
			'value' => 'Foo',
			'type' => 'option',
			'custom' => 'Bar',
			'user_id' => self::$admin_id,
		);

		$this->assertEquals( $expected_params, $data['blogname'] );
	}

	/**
	 * Test that it's possible to delete nav menu item with customize_changeset.
	 */
	public function test_update_item_delete_nav_menu() {
		wp_set_current_user( self::$admin_id );
		$menu_id = wp_create_nav_menu( 'menu' );

		$changeset_data = array(
			'nav_menu[' . $menu_id . ']' => array(
				'value' => false,
				'type' => 'nav_menu',
			),
		);

		$uuid = wp_generate_uuid4();
		$this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'draft',
			'post_content' => wp_json_encode( $changeset_data ),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/customize/v1/changesets/%s', $uuid ) );
		$request->set_param( 'status', 'publish' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( wp_get_nav_menu_object( $menu_id ) );

	}

	/**
	 * Test delete_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::delete_item()
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_param( 'force', false );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'trash', $data['status'] );

		$this->assertSame( 'trash', get_post_status( $manager->find_changeset_post_id( $manager->changeset_uuid() ) ) );
	}

	/**
	 * Test delete_item() by a user without capabilities.
	 */
	public function test_delete_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_param( 'force', false );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );

		$this->assertNotSame( 'trash', get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * Test delete_item() with `$force = true`.
	 */
	public function test_force_delete_item() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous'] );

		$this->assertNull( $manager->find_changeset_post_id( $manager->changeset_uuid() ) );
	}


	/**
	 * Test delete_item() with `$force = true` by a user without capabilities.
	 */
	public function test_force_delete_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * Test delete_item() where the item is already in the trash.
	 */
	public function test_delete_item_already_trashed() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_param( 'force', false );

		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_already_trashed', $response, 410 );
	}

	/**
	 * Test delete_item() by a user without capabilities where the item is already in the trash.
	 */
	public function test_delete_item_already_trashed_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		wp_trash_post( $manager->find_changeset_post_id( $manager->changeset_uuid() ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * Test delete_item with an invalid changeset ID.
	 */
	public function test_delete_item_invalid_id() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', wp_generate_uuid4() ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_uuid', $response, 404 );
	}

	/**
	 * Test delete_item by a user without capabilities with an invalid changeset ID.
	 */
	public function test_delete_item_invalid_id_without_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/customize/v1/changesets/%s', wp_generate_uuid4() ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_uuid', $response, 404 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );
		$uuid = wp_generate_uuid4();

		$settings = wp_json_encode( array(
			self::ALLOWED_TEST_SETTING_ID => array(
				'value' => 'Foo',
			),
		) );

		$customize_changeset = $this->factory()->post->create_and_get( array(
			'post_type' => 'customize_changeset',
			'post_name' => $uuid,
			'post_status' => 'auto-draft',
			'post_content' => $settings,
		) );

		$changeset_endpoint = new WP_REST_Customize_Changesets_Controller();
		$request = new WP_REST_Request();

		$response = $changeset_endpoint->prepare_item_for_response( $customize_changeset, $request );
		$data = $response->get_data();

		$this->assertSame( $uuid, $data['uuid'] );
		$this->assertSame( 'auto-draft', $data['status'] );
		$this->assertTrue( isset( $data['settings'][ self::ALLOWED_TEST_SETTING_ID ] ) );
	}
}
