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
	protected static $subscriber_id;

	/**
	 * Admin user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
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

		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/changesets' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/changesets/' . $manager->changeset_uuid() );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {

		// @todo Add all properties.
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/changesets' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 15, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'title', $properties );
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
	 * Test that changesets cannot be created with update_item() when the user lacks capabilities.
	 */
	public function test_update_item_cannot_create_changeset_post() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_create_changeset_post', $response, 403 );

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );
		$this->assertEmpty( $manager->changeset_post_id() );
	}

	/**
	 * Test that changesets cannot be updated with update_item() when the user lacks capabilities.
	 */
	public function test_update_item_cannot_edit_changeset_post() {
		wp_set_current_user( self::$subscriber_id );

		$manager_before = new WP_Customize_Manager();
		$manager_before->save_changeset_post();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager_before->changeset_uuid() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_edit_changeset_post', $response, 403 );

		$manager_after = new WP_Customize_Manager( array(
			'changeset_uuid' => $manager_before->changeset_uuid(),
		) );
		$this->assertSame( $manager_before->changeset_data(), $manager_after->changeset_data() );
	}

	/**
	 * Test that update_item() rejects invalid changeset data.
	 */
	public function test_update_item_invalid_changeset_data() {
		wp_set_current_user( self::$admin_id );

		$manager_before = new WP_Customize_Manager();
		$manager_before->save_changeset_post( array(
			'basic_option' => array(
				'value' => 'Foo',
			),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager_before->changeset_uuid() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => '[MALFORMED]',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response, 403 );

		$manager_after = new WP_Customize_Manager( array(
			'changeset_uuid' => $manager_before->changeset_uuid(),
		) );
		$this->assertSame( $manager_before->changeset_data(), $manager_after->changeset_data() );
	}

	/**
	 * Test that changeset titles can be updated with update_item().
	 */
	public function test_update_item_with_title() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$title_before = 'Foo';
		$title_after = 'Bar';

		$manager->save_changeset_post( array(
			'title' => $title_before,
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'title' => $title_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['title'], $title_after );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_title, $title_after );
	}

	/**
	 * Test that update_item() rejects nonexistant and disallowed post statuses.
	 *
	 * TODO: Another test when the UUID is not saved to verify no post is created.
	 *
	 * @param string $bad_status Bad status.
	 *
	 * @dataProvider data_bad_customize_changeset_status
	 */
	public function test_update_item_bad_customize_changeset_status( $bad_status ) {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $bad_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bad_customize_changeset_status', $response, 400 );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * Bad changeset statuses.
	 */
	public function data_bad_customize_changeset_status() {
		return array(
			// Doesn't exist.
			rand_str(),
			// Not in the whitelist.
			'trash',
		);
	}

	/**
	 * Test that update_item() rejects publishing changesets if the user lacks capabilities.
	 *
	 * TODO: Another test when the UUID is not saved to verify no post is created.
	 *
	 * @param string $publish_status Publish Status.
	 * @dataProvider data_published_changeset_status
	 */
	public function test_update_item_changeset_publish_unauthorized( $publish_status ) {
		// TODO: Allow the user to create changesets but not publish.
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $publish_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'changeset_publish_unauthorized', $response, 403 );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * "Published" changeset statuses.
	 */
	public function data_published_changeset_status() {
		return array(
			'publish',
			'future',
		);
	}

	/**
	 * Test that changeset dates can be updated with update_item().
	 */
	public function test_update_item_with_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;
		$date_after = date( 'Y-m-d H:i:s', ( strtotime( $date_before ) + YEAR_IN_SECONDS ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date' => $date_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['date_gmt'], $date_after );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_date_gmt, $date_after );
	}

	/**
	 * Test that a update_item() can change a 'future' changeset to 'draft', keeping the date.
	 */
	public function test_update_item_future_to_draft_keeps_post_date() {
		wp_set_current_user( self::$admin_id );

		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 year' ) );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date' => $future_date,
			'status' => 'future',
		) );

		$status_after = 'draft';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $status_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $status_after, get_post_status( $manager->changeset_post_id() ) );
		$this->assertSame( $future_date, get_post( $manager->changeset_post_id() )->post_date );
	}

	/**
	 * Test that update_item() rejects invalid changeset dates.
	 *
	 * TODO: Another test when the UUID is not saved to verify no post is created.
	 */
	public function test_update_item_bad_customize_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date' => 'BAD DATE',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bad_customize_changeset_date', $response, 400 );
		$this->assertSame( $date_before, get_post( $manager->changeset_post_id() )->post_date_gmt );
	}

	/**
	 * Test that publishing a changeset with update_item() returns a 'publish' status.
	 */
	public function test_update_item_status_is_publish_after_publish() {
		if ( post_type_supports( 'customize_changeset', 'revisions' ) ) {
			$this->markTestSkipped( 'Changesets are not trashed when revisions are enabled.' );
		}

		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'publish', $data['status'] );
	}

	/**
	 * Test that publishing a changeset with update_item() returns a new changeset UUID.
	 */
	public function test_update_item_has_next_changeset_id_after_publish() {
		wp_set_current_user( self::$admin_id );

		$uuid_before = wp_generate_uuid4();

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid_before,
		) );
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid_before ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( isset( $data['next_changeset_uuid'] ) );
		$this->assertNotSame( $data['next_changeset_uuid'], $uuid_before );
	}

	/**
	 * Test that publishing a changeset with update_item() updates a valid setting.
	 */
	public function test_update_item_with_bloginfo() {
		wp_set_current_user( self::$admin_id );

		$blogname_after = get_option( 'blogname' ) . ' Amended';

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'data' => array(
				'blogname' => array(
					'value' => $blogname_after,
				),
			),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $blogname_after, get_option( 'blogname' ) );
	}

	/**
	 * Test that update_item() returns setting validities.
	 */
	public function test_update_item_setting_validities() {
		wp_set_current_user( self::$admin_id );

		$bad_setting = rand_str();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', wp_generate_uuid4() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				$bad_setting => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( isset( $data['setting_validities'][ $bad_setting ]['unrecognized'] ) );
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

		$manager = new WP_Customize_Manager();

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

		$manager = new WP_Customize_Manager();

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

		$manager = new WP_Customize_Manager();

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

		$manager = new WP_Customize_Manager();

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

		$manager = new WP_Customize_Manager();
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
