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
class WP_Test_REST_Customize_Controls_Controller extends WP_Test_REST_Controller_TestCase {

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
	 * @param WP_UnitTest_Factory $factory Factory.
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
	 * Test control ID.
	 */
	const TEST_SETTING_ID = 'test_setting';

	/**
	 * Add custom control for testing.
	 *
	 * @param WP_Customize_Manager $wp_customize WP_Customize_Manager.
	 */
	public function add_test_customize_settings( $wp_customize ) {
		$wp_customize->add_panel( 'test_panel', array(
			'name' => 'Test Panel',
			'description' => 'Test Panel',
			'priority' => 100,
		) );

		// Add section to the panel.
		$wp_customize->add_section( 'test_section', array(
			'title' => 'Test Section',
			'panel' => 'test_panel',
			'priority' => 100,
		) );

		// Add setting.
		$wp_customize->add_setting( self::TEST_SETTING_ID, array(
			'type' => 'option',
			'capability' => 'manage_options',
			'default' => 'Test Setting',
			'sanitize_callback' => 'sanitize_text',
		) );

		// Add control.
		$wp_customize->add_control( self::TEST_SETTING_ID, array(
			'type' => 'textarea',
			'section' => 'test_section',
		) );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Controls_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/customize/v1/controls', $routes );
		$this->assertArrayHasKey( '/customize/v1/controls/(?P<control>[\w-]+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Controls_Controller::get_context_param()
	 */
	public function test_context_param() {

		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/controls' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/controls/test' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test get_item_schema.
	 *
	 * @covers WP_REST_Customize_Controls_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$changeset_controller = new WP_REST_Customize_Controls_Controller();
		$schema = $changeset_controller->get_item_schema();
		$properties = $schema['properties'];

		$this->assertSame( 10, count( $properties ) );

		$this->assertArrayHasKey( 'allow_addition', $properties );
		$this->assertSame( 'boolean', $properties['allow_addition']['type'] );

		$this->assertArrayHasKey( 'choices', $properties );
		$this->assertSame( 'array', $properties['choices']['type'] );

		$this->assertArrayHasKey( 'description', $properties );
		$this->assertSame( 'string', $properties['description']['type'] );

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertSame( 'string', $properties['id']['type'] );

		$this->assertArrayHasKey( 'input_attrs', $properties );
		$this->assertSame( 'object', $properties['input_attrs']['type'] );

		$this->assertArrayHasKey( 'label', $properties );
		$this->assertSame( 'string', $properties['label']['type'] );

		$this->assertArrayHasKey( 'priority', $properties );
		$this->assertSame( 'integer', $properties['priority']['type'] );

		$this->assertArrayHasKey( 'section', $properties );
		$this->assertSame( 'string', $properties['section']['type'] );

		$this->assertArrayHasKey( 'settings', $properties );
		$this->assertSame( 'array', $properties['settings']['type'] );

		$this->assertArrayHasKey( 'type', $properties );
		$this->assertSame( 'string', $properties['type']['type'] );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Controls_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/controls/%s', self::TEST_SETTING_ID ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SETTING_ID, $data['id'] );
	}

	/**
	 * Test getting a non-existing control.
	 */
	public function test_get_item_missing() {
		wp_set_current_user( self::$admin_id );

		$invalid_control_id = 'qwertyuiop987654321'; // Probably doesn't exist.
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/controls/%s', $invalid_control_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_control_invalid_id', $response, 404 );
	}

	/**
	 * Test that getting a single control without permissions is forbidden.
	 */
	public function test_get_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/controls/%s', self::TEST_SETTING_ID ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Controls_Controller::get_items()
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/controls' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$test_control_exists = false;
		foreach ( $data as $control ) {
			if ( self::TEST_SETTING_ID === $control['id'] ) {
				$test_control_exists = true;
				break;
			}
		}
		$this->assertTrue( $test_control_exists );
	}

	/**
	 * Test that getting controls without permissions is not authorized.
	 */
	public function test_get_items_without_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/controls' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Controls_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );
		$control_endpoint = new WP_REST_Customize_Controls_Controller();
		$request = new WP_REST_Request();
		$wp_customize = $control_endpoint->ensure_customize_manager();

		$test_control = $wp_customize->get_control( self::TEST_SETTING_ID );

		$response = $control_endpoint->prepare_item_for_response( $test_control, $request );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SETTING_ID, $data['id'] );
		$this->assertTrue( in_array( 'test_setting', $data['settings'], true ) );
		$this->assertEquals( 'test_section', $data['section'] );
	}

	/**
	 * Test create items is not applicable for controls.
	 */
	public function test_create_item() {
		/** Controls can't be created */
	}

	/**
	 * Test update items is not applicable for controls.
	 */
	public function test_update_item() {
		/** Controls can't be updated */
	}

	/**
	 * Test delete item is not applicable for controls.
	 */
	public function test_delete_item() {
		/** Controls can't be deleted */
	}
}
