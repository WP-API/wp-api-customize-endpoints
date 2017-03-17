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
	 * Admin user ID.
	 *
	 * @var int
	 */
	static $admin_user_id;

	/**
	 * Set up before class.
	 */
	static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$admin_user_id = self::factory()->user->create( array(
			'role' => 'administrator',
		) );
	}

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();

		$this->admin_id = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/customize/changesets', $routes );
		$this->assertArrayHasKey( '/wp/v2/customize/changesets/(?P<uuid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}+)', $routes );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$this->markTestIncomplete();
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item()
	 */
	public function test_get_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_items()
	 */
	public function test_get_items() {
		$this->markTestIncomplete();
	}

	/**
	 * Test create_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::create_item()
	 */
	public function test_create_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test update_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::update_item()
	 */
	public function test_update_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test delete_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::delete_item()
	 */
	public function test_delete_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_context_param()
	 */
	public function test_context_param() {
		$this->markTestIncomplete();
	}
}
