<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Controls_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * Class WP_Test_REST_Customize_Controls_Controller.
 *
 * @group restapi
 */
class WP_Test_REST_Customize_Partials_Controller extends WP_Test_REST_Controller_TestCase {

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
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		}

		self::$subscriber_id = $factory->user->create( array(
			'role' => 'subscriber',
		) );

		self::$admin_id = $factory->user->create( array(
			'role' => 'administrator',
		) );
	}

	/**
	 * Setup.
	 */
	public function setUp() {
		parent::setUp();
		add_action( 'customize_register', array( $this, 'add_test_customize_settings' ) );
	}

	/**
	 * Test setting ID.
	 */
	const TEST_SETTING_ID = 'test_setting';

	/**
	 * Test partial ID.
	 */
	const TEST_DYNAMIC_PARTIAL_ID = 'test_partial';

	/**
	 * Add custom control for testing.
	 *
	 * @param object $wp_customize WP_Customize_Manager.
	 */
	public function add_test_customize_settings( $wp_customize ) {

		// Add setting.
		$wp_customize->add_setting( self::TEST_SETTING_ID, array(
			'type'              => 'option',
			'capability'        => 'manage_options',
			'default'           => 'Test Setting',
			'sanitize_callback' => 'sanitize_text',
		) );

		// Add partial.
		$wp_customize->selective_refresh->add_partial( self::TEST_SETTING_ID, array(
			'settings'            => array( self::TEST_SETTING_ID ),
			'selector'            => '.custom-selector',
			'container_inclusive' => true,
		) );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Partials_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/customize/v1/partials', $routes );
		$this->assertArrayHasKey( '/customize/v1/partials/(?P<partial>[\w-|\[\]]+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Partials_Controller::get_context_param()
	 */
	public function test_context_param() {

		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/partials' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/partials/test' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test get_item_schema.
	 *
	 * @covers WP_REST_Customize_Partials_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$changeset_controller = new WP_REST_Customize_Partials_Controller();
		$schema = $changeset_controller->get_item_schema();
		$properties = $schema['properties'];

		$this->assertSame( 6, count( $properties ) );

		$this->assertArrayHasKey( 'fallback_refresh', $properties );
		$this->assertSame( 'boolean', $properties['fallback_refresh']['type'] );

		$this->assertArrayHasKey( 'container_inclusive', $properties );
		$this->assertSame( 'boolean', $properties['container_inclusive']['type'] );

		$this->assertArrayHasKey( 'selector', $properties );
		$this->assertSame( 'string', $properties['selector']['type'] );

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertSame( 'string', $properties['id']['type'] );

		$this->assertArrayHasKey( 'settings', $properties );
		$this->assertSame( 'array', $properties['settings']['type'] );

		$this->assertArrayHasKey( 'type', $properties );
		$this->assertSame( 'string', $properties['type']['type'] );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Partials_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/partials/%s', self::TEST_SETTING_ID ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SETTING_ID, $data['id'] );
	}

	/**
	 * Filter partial args.
	 *
	 * @param bool   $args If add dynamic partial.
	 * @param string $partial_id Partial ID.
	 * @return bool|array False or dynamic partial args.
	 */
	public function filter_customize_dynamic_partial_args( $args, $partial_id ) {
		if ( self::TEST_DYNAMIC_PARTIAL_ID === $partial_id ) {
			return array(
				'settings' => array( self::TEST_SETTING_ID ),
			);
		}
		return false;
	}

	/**
	 * Test getting dynamic partial with 'customize_dynamic_partial_args'.
	 */
	public function test_get_item_with_dynamic_partial_filter() {

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/partials/%s', self::TEST_DYNAMIC_PARTIAL_ID ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_not_found', $response, 404 );

		add_filter( 'customize_dynamic_partial_args', array( $this, 'filter_customize_dynamic_partial_args' ), 10, 2 );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/partials/%s', self::TEST_DYNAMIC_PARTIAL_ID ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( self::TEST_DYNAMIC_PARTIAL_ID, $data['id'] );

		remove_filter( 'customize_dynamic_partial_args', array( $this, 'filter_customize_dynamic_partial_args' ), 10 );
	}

	/**
	 * Test getting a non-existing partial.
	 */
	public function test_get_item_missing() {
		wp_set_current_user( self::$admin_id );

		$invalid_partial_id = 'qwertyuiop987654321'; // Probably doesn't exist.
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/partials/%s', $invalid_partial_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_not_found', $response, 404 );
	}

	/**
	 * Test that getting a single partial without permissions is forbidden.
	 */
	public function test_get_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/partials/%s', self::TEST_SETTING_ID ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Partials_Controller::get_items()
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/partials' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$test_partial_exists = false;
		foreach ( $data as $partial ) {
			if ( self::TEST_SETTING_ID === $partial['id'] ) {
				$test_partial_exists = true;
				break;
			}
		}
		$this->assertTrue( $test_partial_exists );
	}

	/**
	 * Test that getting partials without permissions is not authorized.
	 */
	public function test_get_items_without_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/partials' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Partials_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );
		$partial_endpoint = new WP_REST_Customize_Partials_Controller();
		$request = new WP_REST_Request();
		$wp_customize = $partial_endpoint->ensure_customize_manager();

		$test_partial = $wp_customize->selective_refresh->get_partial( self::TEST_SETTING_ID );

		$response = $partial_endpoint->prepare_item_for_response( $test_partial, $request );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SETTING_ID, $data['id'] );
		$this->assertTrue( in_array( self::TEST_SETTING_ID, $data['settings'], true ) );
		$this->assertEquals( '.custom-selector', $data['selector'] );
	}

	/**
	 * Test create items is not applicable for partials.
	 */
	public function test_create_item() {
		/** Partials can't be created */
	}

	/**
	 * Test update items is not applicable for partials.
	 */
	public function test_update_item() {
		/** Partials can't be updated */
	}

	/**
	 * Test delete item is not applicable for partials.
	 */
	public function test_delete_item() {
		/** Partials can't be deleted */
	}
}
