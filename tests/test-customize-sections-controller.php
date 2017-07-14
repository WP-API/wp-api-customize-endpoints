<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Sections_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * Class WP_Test_REST_Customize_Sections_Controller.
 *
 * @group restapi
 */
class WP_Test_REST_Customize_Sections_Controller extends WP_Test_REST_Controller_TestCase {

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
	 * Clean up global scope.
	 */
	function clean_up_global_scope() {
		$GLOBALS['wp_customize'] = null; // WPCS: override global ok.
		parent::clean_up_global_scope();
	}

	/**
	 * Test section ID.
	 */
	const TEST_SECTION_ID = 'test_section';

	/**
	 * Add custom section for testing.
	 *
	 * @param WP_Customize_Manager $wp_customize WP_Customize_Manager.
	 */
	public function add_test_customize_settings( $wp_customize ) {

		// Add section.
		$wp_customize->add_section( self::TEST_SECTION_ID, array(
			'title' => 'Test Section',
			'priority' => 100,
		) );

		$wp_customize->add_setting( 'test_control', array(
			'default' => '#000',
			'sanitize_callback' => 'sanitize_hex_color',
		) );

		$wp_customize->add_control( 'test_control', array(
			'section' => self::TEST_SECTION_ID,
			'type' => 'textarea',
		) );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Sections_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/customize/v1/sections', $routes );
		$this->assertArrayHasKey( '/customize/v1/sections/(?P<section>[\w-|\[\]]+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Sections_Controller::get_context_param()
	 */
	public function test_context_param() {

		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/sections' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$request = new WP_REST_Request( 'OPTIONS', '/customize/v1/sections/test' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test get_item_schema.
	 *
	 * @covers WP_REST_Customize_Sections_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$changeset_controller = new WP_REST_Customize_Sections_Controller();
		$schema = $changeset_controller->get_item_schema();
		$properties = $schema['properties'];

		$this->assertEquals( 9, count( $properties ) );

		$this->assertArrayHasKey( 'description', $properties );
		$this->assertSame( 'string', $properties['description']['type'] );

		$this->assertArrayHasKey( 'title', $properties );
		$this->assertSame( 'string', $properties['title']['type'] );

		$this->assertArrayHasKey( 'priority', $properties );
		$this->assertSame( 'integer', $properties['priority']['type'] );

		$this->assertArrayHasKey( 'controls', $properties );
		$this->assertSame( 'array', $properties['controls']['type'] );

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertSame( 'string', $properties['id']['type'] );

		$this->assertArrayHasKey( 'theme_supports', $properties );
		$this->assertSame( 'array', $properties['theme_supports']['type'] );

		$this->assertArrayHasKey( 'type', $properties );
		$this->assertSame( 'string', $properties['type']['type'] );

		$this->assertArrayHasKey( 'description_hidden', $properties );
		$this->assertSame( 'boolean', $properties['description_hidden']['type'] );

		$this->assertArrayHasKey( 'panel', $properties );
		$this->assertSame( 'object', $properties['panel']['type'] );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Sections_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/sections/%s', self::TEST_SECTION_ID ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SECTION_ID, $data['id'] );
		$this->assertTrue( in_array( 'test_control', $data['controls'], true ) );
	}

	/**
	 * Test getting a non-existing section.
	 */
	public function test_get_item_invalid_id() {
		wp_set_current_user( self::$admin_id );

		$invalid_section_id = 'qwertyuiop987654321'; // Probably doesn't exist.
		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/sections/%s', $invalid_section_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_section_invalid_id', $response, 404 );
	}

	/**
	 * Test that getting a single section without permissions is forbidden.
	 */
	public function test_get_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/customize/v1/sections/%s', self::TEST_SECTION_ID ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_section_invalid_id', $response, 404 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Sections_Controller::get_items()
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/sections' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$test_section_exists = false;
		foreach ( $data as $section ) {
			if ( self::TEST_SECTION_ID === $section['id'] ) {
				$test_section_exists = true;
				break;
			}
		}
		$this->assertTrue( $test_section_exists );
	}

	/**
	 * Test that getting sections without permissions is not authorized.
	 */
	public function test_get_items_without_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', '/customize/v1/sections' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Sections_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );
		$section_endpoint = new WP_REST_Customize_Sections_Controller();
		$request = new WP_REST_Request();
		$wp_customize = $section_endpoint->ensure_customize_manager();

		$test_section = $wp_customize->get_section( self::TEST_SECTION_ID );

		$response = $section_endpoint->prepare_item_for_response( $test_section, $request );
		$data = $response->get_data();

		$this->assertSame( self::TEST_SECTION_ID, $data['id'] );
		$this->assertSame( 100, $data['priority'] );
		$this->assertTrue( in_array( 'test_control', $data['controls'], true ) );
	}

	/**
	 * Test create items is not applicable for sections.
	 */
	public function test_create_item() {
		/** Sections can't be created */
	}

	/**
	 * Test update items is not applicable for sections.
	 */
	public function test_update_item() {
		/** Sections can't be updated */
	}

	/**
	 * Test delete item is not applicable for sections.
	 */
	public function test_delete_item() {
		/** Sections can't be deleted */
	}
}
