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
		$manager = new WP_Customize_Manager();
		$manager->add_panel( 'test' );
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
		$this->markTestIncomplete();
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_item()
	 */
	public function test_get_item() {
		$this->markTestIncomplete();
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
