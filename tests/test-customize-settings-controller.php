<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Settings_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * Class WP_Test_REST_Customize_Settings_Controller.
 *
 * @group restapi
 */
class WP_Test_REST_Customize_Settings_Controller extends WP_Test_REST_Controller_TestCase {

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
	 * Add custom setting for testing.
	 *
	 * @param object $wp_customize WP_Customize_Manager.
	 */
	public function add_test_customize_settings( $wp_customize ) {
		$wp_customize->add_panel( 'test_panel', array(
			'name' => 'Test Panel',
			'description' => 'Test Panel',
			'priority' => 100,
		) );

		// Add setting to the panel.
		$wp_customize->add_section( 'test_section', array(
			'title' => 'Test Section',
			'panel' => 'test_panel',
			'priority' => 100,
		) );

		$wp_customize->add_setting( self::TEST_SETTING_ID , array(
			'default'   => '#000000',
			'transport' => 'refresh',
		) );

		$wp_customize->add_control( self::TEST_SETTING_ID, array(
			'type' => 'textarea',
			'section' => 'test_section',
		) );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Settings_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/customize/v1/settings', $routes );
		$this->assertArrayHasKey( '/customize/v1/settings/(?P<setting>[\w-|\[\]]+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Settings_Controller::get_context_param()
	 */
	public function test_context_param() {
		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/settings' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/settings/test' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test get_item_schema.
	 *
	 * @covers WP_REST_Customize_Settings_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$changeset_controller = new WP_REST_Customize_Settings_Controller();
		$schema = $changeset_controller->get_item_schema();
		$properties = $schema['properties'];

		$this->markTestIncomplete();
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Settings_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/settings/%s', self::TEST_SETTING_ID ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SETTING_ID, $data['id'] );
		$this->markTestIncomplete();
	}

	/**
	 * Test getting a non-existing setting.
	 */
	public function test_get_item_invalid_id() {
		wp_set_current_user( self::$admin_id );

		$invalid_setting_id = 'qwertyuiop987654321'; // Probably doesn't exist.
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/settings/%s', $invalid_setting_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_setting_invalid_id', $response, 403 );
	}

	/**
	 * Test that getting a single setting without permissions is forbidden.
	 */
	public function test_get_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/settings/%s', self::TEST_SETTING_ID ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Settings_Controller::get_items()
	 */
	public function test_get_items() {
		$this->markTestIncomplete();
	}

	/**
	 * Test that getting settings without permissions is not authorized.
	 */
	public function test_get_items_without_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/settings' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test updating setting value.
	 */
	public function test_update_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Settings_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test create items is not applicable for settings.
	 */
	public function test_create_item() {
		/** Settings can't be created */
	}

	/**
	 * Test delete item is not applicable for settings.
	 */
	public function test_delete_item() {
		/** Settings can't be deleted */
	}
}
