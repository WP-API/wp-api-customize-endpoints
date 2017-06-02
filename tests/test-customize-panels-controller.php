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
	 * Test panel slug.
	 */
	const TEST_PANEL_SLUG = 'test_panel';

	/**
	 * Add custom panels for testing.
	 *
	 * @param object $wp_customize WP_Customize_Manager.
	 */
	protected function add_test_customize_settings( $wp_customize ) {
		$wp_customize->add_panel( self::TEST_PANEL_SLUG, array(
			'name' => 'Test Panel',
			'description' => 'Test Panel',
			'priority' => 100,
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
		$this->assertArrayHasKey( '/customize/v1/panels/(?P<panel>[\w-]+)', $routes );
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
		$this->assertEquals( array( 'view', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/panels/test' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
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

		$this->assertEquals( 7, count( $properties ) );

		$this->assertArrayHasKey( 'description', $properties );
		$this->assertSame( 'string', $properties['description']['type'] );

		$this->assertArrayHasKey( 'name', $properties );
		$this->assertSame( 'string', $properties['name']['type'] );

		$this->assertArrayHasKey( 'priority', $properties );
		$this->assertSame( 'integer', $properties['priority']['type'] );

		$this->assertArrayHasKey( 'sections', $properties );
		$this->assertSame( 'object', $properties['sections']['type'] );

		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertSame( 'string', $properties['slug']['type'] );

		$this->assertArrayHasKey( 'theme_supports', $properties );
		$this->assertSame( 'object', $properties['theme_supports']['type'] );

		$this->assertArrayHasKey( 'type', $properties );
		$this->assertSame( 'string', $properties['type']['type'] );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', self::TEST_PANEL_SLUG ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( 1, count( $data ) );
		$this->assertSame( self::TEST_PANEL_SLUG, $data[0]['slug'] );
	}

	/**
	 * Test that edit context param is not allowed.
	 */
	public function test_get_item_unauthorized_context() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', self::TEST_PANEL_SLUG ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 403 );
	}

	/**
	 * Test getting a non-existing panel.
	 */
	public function test_get_item_invalid_slug() {
		wp_set_current_user( self::$admin_id );

		$invalid_panel_id = 'qwertyuiop987654321'; // Probably doesn't exist.
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', $invalid_panel_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_panel_invalid_slug', $response, 404 );
	}

	/**
	 * Test that getting a single panel without permissions is forbidden.
	 */
	public function test_get_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/panels/%s', self::TEST_PANEL_SLUG ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_items()
	 */
	public function test_get_items() {
		$this->markTestIncomplete();
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
		$this->markTestIncomplete();
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
