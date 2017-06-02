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
		$this->markTestIncomplete();
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Panels_Controller::get_context_param()
	 */
	public function test_context_param() {
		$this->markTestIncomplete();
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
