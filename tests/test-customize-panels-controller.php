<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Panels_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * Class WP_Test_REST_Customize_Panels_Controller.
 *
 * @group restapi
 */
class WP_Test_REST_Customize_Panels_Controller extends WP_Test_REST_Controller_TestCase {

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
	 * Test panel slug.
	 */
	const TEST_PANEL_ID = 'test_panel';

	/**
	 * Add custom panels for testing.
	 *
	 * @param WP_Customize_Manager $wp_customize WP_Customize_Manager.
	 */
	public function add_test_customize_settings( $wp_customize ) {
		$wp_customize->add_panel( self::TEST_PANEL_ID, array(
			'name' => 'Test Panel',
			'description' => 'Test Panel',
			'priority' => 100,
		) );

		// Add section to the panel.
		$wp_customize->add_section( 'test_section', array(
			'title' => 'Test Section',
			'panel' => self::TEST_PANEL_ID,
		) );

		$wp_customize->add_control( 'test_control', array(
			'type' => 'textarea',
			'section' => 'test_section',
		) );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/customize/v1/panels', $routes );
		$this->assertArrayHasKey( '/customize/v1/panels/(?P<panel>[\w-|\[\]]+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_context_param()
	 */
	public function test_context_param() {
		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/panels' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/panels/test' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test get_item_schema.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$changeset_controller = new WP_REST_Customize_Panels_Controller();
		$schema = $changeset_controller->get_item_schema();
		$properties = $schema['properties'];

		$this->assertEquals( 8, count( $properties ) );

		$this->assertArrayHasKey( 'description', $properties );
		$this->assertSame( 'string', $properties['description']['type'] );

		$this->assertArrayHasKey( 'title', $properties );
		$this->assertSame( 'string', $properties['title']['type'] );

		$this->assertArrayHasKey( 'priority', $properties );
		$this->assertSame( 'integer', $properties['priority']['type'] );

		$this->assertArrayHasKey( 'sections', $properties );
		$this->assertSame( 'array', $properties['sections']['type'] );

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertSame( 'string', $properties['id']['type'] );

		$this->assertArrayHasKey( 'theme_supports', $properties );
		$this->assertSame( 'array', $properties['theme_supports']['type'] );

		$this->assertArrayHasKey( 'type', $properties );
		$this->assertSame( 'string', $properties['type']['type'] );

		$this->assertArrayHasKey( 'auto_expand_sole_section', $properties );
		$this->assertSame( 'boolean', $properties['auto_expand_sole_section']['type'] );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', self::TEST_PANEL_ID ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( self::TEST_PANEL_ID, $data['id'] );
	}

	/**
	 * Test getting a non-existing panel.
	 */
	public function test_get_item_missing() {
		wp_set_current_user( self::$admin_id );

		$invalid_panel_id = 'qwertyuiop987654321'; // Probably doesn't exist.
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', $invalid_panel_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_panel_not_found', $response, 404 );
	}

	/**
	 * Test that getting a single panel without permissions is forbidden.
	 */
	public function test_get_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', self::TEST_PANEL_ID ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_panel_not_found', $response, 404 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_items()
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/panels' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		foreach ( $data as $panel ) {
			$this->assertTrue( in_array( $panel['id'], array( self::TEST_PANEL_ID, 'nav_menus' ), true ) );
		}
	}

	/**
	 * Test that getting panels without permissions is not authorized.
	 */
	public function test_get_items_without_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/panels' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Panels_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );
		$panel_endpoint = new WP_REST_Customize_Panels_Controller();
		$request = new WP_REST_Request();
		$wp_customize = $panel_endpoint->ensure_customize_manager();

		$test_panel = $wp_customize->get_panel( self::TEST_PANEL_ID );

		$response = $panel_endpoint->prepare_item_for_response( $test_panel, $request );
		$data = $response->get_data();

		$this->assertSame( self::TEST_PANEL_ID, $data['id'] );
		$this->assertSame( 100, $data['priority'] );
		$this->assertSame( 'test_section', $data['sections'][0] );
	}

	/**
	 * Test create items is not applicable for panels.
	 */
	public function test_create_item() {
		/** Panels can't be created */
	}

	/**
	 * Test update items is not applicable for panels.
	 */
	public function test_update_item() {
		/** Panels can't be updated */
	}

	/**
	 * Test delete item is not applicable for panels.
	 */
	public function test_delete_item() {
		/** Panels can't be deleted */
	}
}
