<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Admin\AdminPage;
use Rachnix\CheckoutFields\Checkout\Blocks\BlockFieldSupport;
use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Checkout\PreviewMode;
use Rachnix\CheckoutFields\Checkout\WooCoreFieldCatalog;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Rest\PublishController;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Security\RequestContext;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\PreviewService;

/**
 * "Publish changes" (one transaction), the draft preview, and the admin page shell.
 * Runs on the rxcftest_ tables under the rxcftest/v1 namespace.
 */
final class PublishTest extends IntegrationTestCase {

	private const UUID_A = '11111111-1111-4111-8111-111111111111';
	private const UUID_B = '22222222-2222-4222-8222-222222222222';

	private static int $manager = 0;

	private ?\Closure $routes = null;

	private FieldRepository $repository;

	private FieldService $service;

	private AuditLogRepository $audit;

	private PreviewService $previews;

	public static function setUpBeforeClass(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
		self::$manager = self::create_test_user( 'shop_manager' );
	}

	public static function tearDownAfterClass(): void {
		wp_set_current_user( 0 );
		delete_transient( 'rxcf_preview_' . self::$manager );
		self::delete_test_user( self::$manager );
		self::reset_test_schema();
	}

	protected function setUp(): void {
		foreach ( array( self::tables()->fields(), self::tables()->audit_log() ) as $table ) {
			self::db()->query( self::db()->prepare( 'TRUNCATE TABLE %i', $table ) );
		}
		delete_transient( self::CACHE_KEY );

		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$this->repository = new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), new Logger() );
		$this->audit      = new AuditLogRepository( self::db(), self::tables(), new RequestContext(), new Logger() );
		$this->service    = new FieldService( $this->repository, $this->audit, $registry, new PatternLibrary(), new WooCoreFieldCatalog() );
		$this->previews   = new PreviewService( $this->service );

		$controller   = new PublishController( $this->service, $this->previews, new Logger(), 'rxcftest/v1' );
		$this->routes = static function () use ( $controller ): void {
			$controller->register_routes();
		};
		add_action( 'rest_api_init', $this->routes );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh REST server per test.
		rest_get_server();
		wp_set_current_user( self::$manager );
	}

	protected function tearDown(): void {
		remove_action( 'rest_api_init', $this->routes );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset for other tests.
		unset( $_GET[ PreviewService::QUERY_ARG ], $_GET[ PreviewService::NONCE_ARG ] );
		remove_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
		remove_filter( 'show_admin_bar', '__return_false' );
		wp_set_current_user( 0 );
	}

	public function test_publish_applies_every_operation_in_order(): void {
		$response = $this->call(
			'publish',
			array(
				$this->create( self::UUID_A, 'gift_note' ),
				$this->create( self::UUID_B, 'floor' ),
				array(
					'op'      => 'update',
					'uuid'    => self::UUID_A,
					'changes' => array( 'label' => 'Gift message' ),
				),
				array(
					'op'      => 'reorder',
					'section' => 'order',
					'order'   => array( self::UUID_B, self::UUID_A ),
				),
				array(
					'op'      => 'core',
					'section' => 'billing',
					'key'     => 'billing_city',
					'changes' => array( 'label' => 'Town' ),
				),
			)
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$order = $this->repository->all()->in_section( \Rachnix\CheckoutFields\Domain\SectionKey::from_string( 'order' ) )->to_list();
		$this->assertSame( array( 'floor', 'gift_note' ), array_map( static fn( $f ) => $f->key, $order ) );
		$this->assertSame( 'Gift message', $this->repository->find( self::UUID_A )?->label );
		$this->assertCount( 3, $response->get_data()['fields'] );
	}

	public function test_one_invalid_operation_saves_nothing(): void {
		$response = $this->call(
			'publish',
			array(
				$this->create( self::UUID_A, 'gift_note' ),
				$this->create( self::UUID_B, 'payment_method' ), // Reserved key.
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'operations.1.key', $response->as_error()->get_error_data()['params'] );
		$this->assertCount( 0, $this->repository->all(), 'The first, valid create was rolled back too.' );
		$this->assertSame( array(), $this->audit->latest(), 'Its audit entry as well.' );
	}

	public function test_operations_on_missing_fields_are_reported(): void {
		$response = $this->call(
			'publish',
			array(
				array(
					'op'   => 'delete',
					'uuid' => self::UUID_A,
				),
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'operations.0.uuid', $response->as_error()->get_error_data()['params'] );
	}

	public function test_malformed_operations_are_refused(): void {
		$this->assertSame( 400, $this->call( 'publish', array( array( 'op' => 'drop_table' ) ) )->get_status() );
		$this->assertSame( 400, $this->call( 'publish', array( 'not an object' ) )->get_status() );
		$this->assertSame( 400, $this->call( 'publish', array_fill( 0, PublishController::MAX_OPERATIONS + 1, $this->create( self::UUID_A, 'x_field' ) ) )->get_status() );
	}

	public function test_a_client_uuid_cannot_take_over_an_existing_field(): void {
		$this->call( 'publish', array( $this->create( self::UUID_A, 'gift_note' ) ) );
		$response = $this->call( 'publish', array( $this->create( self::UUID_A, 'other_note' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'operations.0.uuid', $response->as_error()->get_error_data()['params'] );
	}

	public function test_preview_shows_the_draft_without_saving_it(): void {
		$this->call( 'publish', array( $this->create( self::UUID_A, 'gift_note' ) ) );
		$audit_before = count( $this->audit->latest() );

		$draft = $this->service->preview(
			array(
				$this->create( self::UUID_B, 'floor' ),
				array(
					'op'      => 'update',
					'uuid'    => self::UUID_A,
					'changes' => array( 'label' => 'Draft label' ),
				),
			),
			self::$manager
		);

		$this->assertCount( 2, $draft );
		$this->assertSame( 'Draft label', $draft->find( self::UUID_A )?->label );
		$this->assertCount( 1, $this->repository->all(), 'Nothing was saved.' );
		$this->assertSame( 'Gift note', $this->repository->find( self::UUID_A )?->label );
		$this->assertCount( $audit_before, $this->audit->latest(), 'No audit entries from a preview.' );
		$cached = get_transient( self::CACHE_KEY );
		$this->assertNotContains( 'floor', is_array( $cached ) ? array_column( $cached, 'field_key' ) : array(), 'Draft rows never reached the cache.' );
	}

	public function test_preview_url_activates_the_draft_only_with_nonce_and_capability(): void {
		$response = $this->call( 'preview', array( $this->create( self::UUID_A, 'gift_note' ) ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$url = $response->get_data()['url'];
		$this->assertStringContainsString( PreviewService::QUERY_ARG . '=1', $url );
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		// Wrong nonce: stored fields only.
		$_GET[ PreviewService::QUERY_ARG ] = '1';
		$_GET[ PreviewService::NONCE_ARG ] = 'bad';
		$mode                              = new PreviewMode( $this->previews, $this->repository );
		$mode->maybe_activate();
		$this->assertFalse( $mode->is_active() );
		$this->assertCount( 0, $this->repository->all() );

		// Right nonce, right user: the draft is shown.
		$_GET[ PreviewService::NONCE_ARG ] = $query[ PreviewService::NONCE_ARG ];
		$mode->maybe_activate();
		$this->assertTrue( $mode->is_active() );
		$this->assertSame( array( 'gift_note' ), array_map( static fn( $f ) => $f->key, $this->repository->all()->to_list() ) );
		$this->assertStringContainsString( 'Orders cannot be placed', $mode->order_button() );
	}

	public function test_preview_never_reaches_other_users(): void {
		$response = $this->call( 'preview', array( $this->create( self::UUID_A, 'gift_note' ) ) );
		wp_parse_str( (string) wp_parse_url( $response->get_data()['url'], PHP_URL_QUERY ), $query );

		$customer = self::create_test_user( 'customer' );
		wp_set_current_user( $customer );
		$_GET[ PreviewService::QUERY_ARG ] = '1';
		$_GET[ PreviewService::NONCE_ARG ] = $query[ PreviewService::NONCE_ARG ];
		$mode                              = new PreviewMode( $this->previews, $this->repository );
		$mode->maybe_activate();

		$this->assertFalse( $mode->is_active() );
		$this->assertCount( 0, $this->repository->all() );
		self::delete_test_user( $customer );
	}

	public function test_admin_page_config_and_build_notice(): void {
		$page   = new AdminPage( WP_PLUGIN_DIR . '/rachnix-checkout-fields/rachnix-checkout-fields.php', new CheckoutDetector(), new BlockFieldSupport( date_supported: false ) );
		$config = $page->config();

		$this->assertSame( 'rxcf/v1', $config['namespace'] );
		$this->assertFalse( $config['blockDateFields'] );
		$this->assertArrayHasKey( 'checkoutUrl', $config );
		$this->assertFalse( $config['canEditCss'], 'Shop managers cannot edit CSS.' );

		// Scripts load on our screen only.
		$page->enqueue( 'edit.php' );
		$this->assertFalse( wp_script_is( AdminPage::SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * @param string $uuid Client-chosen UUID.
	 * @param string $key  Field key.
	 * @return array<string, mixed>
	 */
	private function create( string $uuid, string $key ): array {
		return array(
			'op'    => 'create',
			'field' => array(
				'uuid'    => $uuid,
				'section' => 'order',
				'key'     => $key,
				'type'    => 'text',
				'label'   => 'Gift note',
			),
		);
	}

	/**
	 * @param string      $route      "publish" or "preview".
	 * @param list<mixed> $operations Operations.
	 */
	private function call( string $route, array $operations ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', "/rxcftest/v1/{$route}" );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'operations' => $operations ) ) );
		return rest_do_request( $request );
	}
}
