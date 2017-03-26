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
	 * Subscriber user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	static $subscriber_id;

	/**
	 * Admin user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	static $admin_id;

	/**
	 * Set up before class.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Deleted by _delete_all_data() in WP_UnitTestCase::tearDownAfterClass().
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
	 * @covers WP_REST_Customize_Changesets_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/customize/changesets', $routes );
		$this->assertArrayHasKey( '/wp/v2/customize/changesets/(?P<uuid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_context_param()
	 */
	public function test_context_param() {
		$this->markTestIncomplete();
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
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new \WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', false );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'trash', $data['status'] );

		$this->assertSame( 'trash', get_post_status( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test delete_item() by a user without capabilities.
	 */
	public function test_delete_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new \WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', false );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );

		$this->assertNotSame( 'trash', get_post_status( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test delete_item() with `$force = true`.
	 */
	public function test_force_delete_item() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new \WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous'] );

		$this->assertNull( $manager->find_changeset_post_id( $uuid ) );
	}


	/**
	 * Test delete_item() with `$force = true` by a user without capabilities.
	 */
	public function test_force_delete_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new \WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test delete_item() where the item is already in the trash.
	 */
	public function test_delete_item_already_trashed() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name'   => $uuid,
			'post_type'   => 'customize_changeset',
		) );

		$manager = new \WP_Customize_Manager();
		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
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

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name'   => $uuid,
			'post_type'   => 'customize_changeset',
		) );

		wp_trash_post( $changeset_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * Test delete_item with an invalid changeset ID.
	 */
	public function test_delete_item_invalid_id() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', wp_generate_uuid4() ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * Test delete_item by a user without capabilities with an invalid changeset ID.
	 */
	public function test_delete_item_invalid_id_without_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', wp_generate_uuid4() ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		$this->markTestIncomplete();
	}
}
