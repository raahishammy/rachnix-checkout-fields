<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Rest\FieldsController;
use Rachnix\CheckoutFields\Rest\SectionsController;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Security\RequestContext;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\PatternLibrary;

/**
 * The API as a shop manager uses it. Controllers run under the `rxcftest/v1`
 * namespace on the `rxcftest_` tables, so the store's data is never touched.
 */
final class FieldsApiTest extends IntegrationTestCase {

	private const NS = '/rxcftest/v1';

	private static int $manager = 0;

	private ?\Closure $routes = null;

	private AuditLogRepository $audit;

	public static function setUpBeforeClass(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
		self::$manager = self::create_test_user( 'shop_manager' );
	}

	public static function tearDownAfterClass(): void {
		wp_set_current_user( 0 );
		self::delete_test_user( self::$manager );
		self::reset_test_schema();
	}

	protected function setUp(): void {
		foreach ( array( self::tables()->fields(), self::tables()->audit_log() ) as $table ) {
			self::db()->query( self::db()->prepare( 'TRUNCATE TABLE %i', $table ) );
		}
		delete_transient( self::CACHE_KEY );

		$logger   = new Logger();
		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$patterns    = new PatternLibrary();
		$this->audit = new AuditLogRepository( self::db(), self::tables(), new RequestContext(), $logger );
		$service     = new FieldService(
			new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), $logger ),
			$this->audit,
			$registry,
			$patterns
		);
		$controllers = array(
			new FieldsController( $service, $registry, $patterns, $logger, 'rxcftest/v1' ),
			new SectionsController( $service, $logger, 'rxcftest/v1' ),
		);

		$this->routes = static function () use ( $controllers ): void {
			foreach ( $controllers as $controller ) {
				$controller->register_routes();
			}
		};
		add_action( 'rest_api_init', $this->routes );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh REST server per test.
		rest_get_server();

		wp_set_current_user( self::$manager );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
	}

	protected function tearDown(): void {
		remove_action( 'rest_api_init', $this->routes );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset for other tests.
		wp_set_current_user( 0 );
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_create_read_update_delete(): void {
		$created = $this->call( 'POST', '/fields', $this->gift_note() );
		$this->assertSame( 201, $created->get_status(), wp_json_encode( $created->get_data() ) );
		$uuid = $created->get_data()['uuid'];
		$this->assertStringEndsWith( "/rxcftest/v1/fields/{$uuid}", $created->get_headers()['Location'] );

		$read = $this->call( 'GET', "/fields/{$uuid}" );
		$this->assertSame( 'Gift note', $read->get_data()['label'] );
		$this->assertEquals( 200, $read->get_data()['settings']['validation'][0]['max'] );

		$updated = $this->call( 'PATCH', "/fields/{$uuid}", array( 'label' => 'Gift message' ) );
		$this->assertSame( 200, $updated->get_status() );
		$this->assertSame( 'Gift message', $updated->get_data()['label'] );
		$this->assertSame( 'textarea', $updated->get_data()['type'], 'Partial update keeps other values.' );
		$this->assertTrue( $updated->get_data()['settings']['required'] );

		$this->assertSame( 200, $this->call( 'DELETE', "/fields/{$uuid}" )->get_status() );
		$this->assertSame( 404, $this->call( 'GET', "/fields/{$uuid}" )->get_status() );
		$this->assertSame( 404, $this->call( 'DELETE', "/fields/{$uuid}" )->get_status() );
	}

	public function test_list_filters_by_section(): void {
		$this->call( 'POST', '/fields', $this->gift_note() );
		$this->call( 'POST', '/fields', array( 'section' => 'billing', 'key' => 'billing_vat', 'type' => 'text' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

		$this->assertCount( 2, $this->call( 'GET', '/fields' )->get_data() );
		$billing = $this->call( 'GET', '/fields', array( 'section' => 'billing' ) )->get_data();
		$this->assertSame( array( 'billing_vat' ), array_column( $billing, 'key' ) );
		$this->assertSame( 400, $this->call( 'GET', '/fields', array( 'section' => 'payment' ) )->get_status() );
	}

	public function test_schema_rejects_malformed_requests(): void {
		$cases = array(
			'unknown type'       => array( 'type' => 'color' ),
			'bad key'            => array( 'key' => 'Gift Note!' ),
			'bad section'        => array( 'section' => 'nowhere' ),
			'missing key'        => array( 'key' => null ),
			'label too long'     => array( 'label' => str_repeat( 'x', 256 ) ),
			'unknown setting'    => array( 'settings' => array( 'eval' => 'x' ) ),
			'bad width'          => array( 'settings' => array( 'width' => 'triple' ) ),
			'unknown pattern'    => array(
				'settings' => array(
					'validation' => array(
						array(
							'rule'    => 'pattern',
							'pattern' => '/(a+)+$/',
						),
					),
				),
			),
			'css injection'      => array( 'settings' => array( 'css_classes' => array( '"><script>' ) ) ),
			'too many rules'     => array(
				'settings' => array(
					'validation' => array_fill(
						0,
						11,
						array(
							'rule' => 'length',
							'max'  => 5,
						)
					),
				),
			),
			'priority as string' => array( 'priority' => 'first' ),
		);

		foreach ( $cases as $name => $change ) {
			$body = array_filter( array_merge( $this->gift_note(), $change ), static fn( $v ) => null !== $v );
			$this->assertSame( 400, $this->call( 'POST', '/fields', $body )->get_status(), $name );
		}
		$this->assertCount( 0, $this->call( 'GET', '/fields' )->get_data(), 'Nothing was created.' );
	}

	public function test_business_rules_are_enforced_with_field_level_messages(): void {
		$cases = array(
			'reserved key'        => array( array( 'key' => 'payment_method' ), 'key' ),
			'select needs choice' => array( array( 'type' => 'select', 'settings' => array( 'options' => array() ) ), 'settings.options' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			'duplicate choices'   => array( array( 'type' => 'select', 'settings' => array( 'options' => array( array( 'value' => 'a' ), array( 'value' => 'a' ) ) ) ), 'settings.options.1' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			'default not choice'  => array( array( 'type' => 'select', 'settings' => array( 'default' => 'z', 'options' => array( array( 'value' => 'a' ) ) ) ), 'settings.default' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			'rule not for type'   => array( array( 'type' => 'text', 'settings' => array( 'validation' => array( array( 'rule' => 'number_range', 'min' => 1 ) ) ) ), 'settings.validation.0' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			'min above max'       => array( array( 'settings' => array( 'validation' => array( array( 'rule' => 'length', 'min' => 9, 'max' => 2 ) ) ) ), 'settings.validation.0' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			'length above cap'    => array( array( 'type' => 'text', 'settings' => array( 'validation' => array( array( 'rule' => 'length', 'max' => 999 ) ) ) ), 'settings.validation.0' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		);

		foreach ( $cases as $name => [ $change, $param ] ) {
			$response = $this->call( 'POST', '/fields', array_merge( $this->gift_note(), $change ) );

			$this->assertSame( 400, $response->get_status(), $name );
			$this->assertSame( 'rxcf_invalid_field', $response->as_error()->get_error_code(), $name );
			$this->assertArrayHasKey( $param, $response->as_error()->get_error_data()['params'] ?? array(), $name . ': ' . wp_json_encode( $response->get_data() ) );
		}
	}

	public function test_same_key_twice_in_a_section_is_refused(): void {
		$this->assertSame( 201, $this->call( 'POST', '/fields', $this->gift_note() )->get_status() );

		$second = $this->call( 'POST', '/fields', $this->gift_note() );
		$this->assertSame( 400, $second->get_status() );
		$this->assertArrayHasKey( 'key', $second->as_error()->get_error_data()['params'] );
	}

	public function test_at_most_two_fields_in_the_orders_list(): void {
		foreach ( array( 'one', 'two', 'three' ) as $i => $name ) {
			$response = $this->call(
				'POST',
				'/fields',
				array(
					'section'  => 'order',
					'key'      => "order_{$name}",
					'type'     => 'text',
					'settings' => array( 'display' => array( 'order_list' => true ) ),
				)
			);
			$this->assertSame( $i < 2 ? 201 : 400, $response->get_status(), $name );
		}
	}

	public function test_hostile_text_is_sanitised_before_storage(): void {
		$response = $this->call(
			'POST',
			'/fields',
			array_merge(
				$this->gift_note(),
				array(
					'label'       => 'Gift <img src=x onerror=alert(1)>note<script>alert(2)</script>',
					'placeholder' => "Robert'); DROP TABLE wp_users;--",
				)
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Gift note', $response->get_data()['label'] );
		$this->assertSame( "Robert'); DROP TABLE wp_users;--", $response->get_data()['placeholder'], 'Stored as data; queries are prepared.' );
	}

	public function test_extensions_cannot_be_written_without_an_add_on(): void {
		$body             = $this->gift_note();
		$body['settings'] = array( 'extensions' => array( 'pro' => array( 'injected' => true ) ) );

		$this->assertSame( array(), $this->call( 'POST', '/fields', $body )->get_data()['settings']['extensions'] );
	}

	public function test_reorder(): void {
		$a = $this->call( 'POST', '/fields', array( 'section' => 'billing', 'key' => 'billing_a', 'type' => 'text' ) )->get_data()['uuid']; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$b = $this->call( 'POST', '/fields', array( 'section' => 'billing', 'key' => 'billing_b', 'type' => 'text' ) )->get_data()['uuid']; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

		$response = $this->call( 'POST', '/fields/reorder', array( 'section' => 'billing', 'order' => array( $b, $a ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertSame( array( 'billing_b', 'billing_a' ), array_column( $response->get_data(), 'key' ) );

		$this->assertSame( 400, $this->call( 'POST', '/fields/reorder', array( 'section' => 'billing', 'order' => array( $a ) ) )->get_status(), 'Incomplete list.' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	public function test_reset_needs_confirmation_and_clears_the_section(): void {
		$this->call( 'POST', '/fields', $this->gift_note() );
		$this->call( 'POST', '/fields', array( 'section' => 'billing', 'key' => 'billing_vat', 'type' => 'text' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

		$this->assertSame( 400, $this->call( 'POST', '/sections/order/reset', array( 'confirm' => false ) )->get_status() );
		$this->assertSame( 400, $this->call( 'POST', '/sections/order/reset' )->get_status() );

		$reset = $this->call( 'POST', '/sections/order/reset', array( 'confirm' => true ) );
		$this->assertSame( 1, $reset->get_data()['deleted'] );
		$this->assertSame( array( 'billing_vat' ), array_column( $this->call( 'GET', '/fields' )->get_data(), 'key' ) );
	}

	public function test_sections_and_types_lists(): void {
		$this->call( 'POST', '/fields', $this->gift_note() );

		$sections = $this->call( 'GET', '/sections' )->get_data();
		$this->assertSame( array( 'billing', 'shipping', 'order', 'contact' ), array_column( $sections, 'key' ) );
		$this->assertSame( 1, $sections[2]['field_count'] );

		$meta = $this->call( 'GET', '/field-types' )->get_data();
		$this->assertCount( 11, $meta['types'] );
		$this->assertContains( 'pin_in', array_column( $meta['patterns'], 'id' ) );
	}

	public function test_every_change_is_audited_without_the_raw_ip(): void {
		$uuid = $this->call( 'POST', '/fields', $this->gift_note() )->get_data()['uuid'];
		$this->call( 'PATCH', "/fields/{$uuid}", array( 'label' => 'Gift message' ) );
		$this->call( 'PATCH', "/fields/{$uuid}", array( 'label' => 'Gift message' ) ); // No change: not logged.
		$this->call( 'DELETE', "/fields/{$uuid}" );

		$entries = $this->audit->latest();
		$this->assertSame( array( 'delete', 'update', 'create' ), array_column( $entries, 'action' ) );
		$this->assertSame( (string) self::$manager, (string) $entries[0]['user_id'] );

		$update = json_decode( (string) $entries[1]['changes'], true );
		$this->assertSame(
			array(
				'before' => array( 'label' => 'Gift note' ),
				'after'  => array( 'label' => 'Gift message' ),
			),
			$update
		);

		foreach ( $entries as $entry ) {
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', (string) $entry['ip_hash'] );
			$this->assertStringNotContainsString( '203.0.113.7', (string) wp_json_encode( $entry ) );
		}
	}

	public function test_purge_removes_only_old_entries(): void {
		$this->call( 'POST', '/fields', $this->gift_note() );
		self::db()->insert(
			self::tables()->audit_log(),
			array(
				'action'      => 'update',
				'object_type' => 'field',
				'object_id'   => 'old',
				'changes'     => '{}',
				'created_at'  => gmdate( 'Y-m-d H:i:s', time() - 91 * DAY_IN_SECONDS ),
			)
		);

		$this->assertSame( 1, $this->audit->purge_older_than( 90 ) );
		$this->assertSame( array( 'create' ), array_column( $this->audit->latest(), 'action' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function gift_note(): array {
		return array(
			'section'  => 'order',
			'key'      => 'gift_note',
			'type'     => 'textarea',
			'label'    => 'Gift note',
			'settings' => array(
				'required'   => true,
				'validation' => array(
					array(
						'rule' => 'length',
						'max'  => 200,
					),
				),
			),
		);
	}

	/**
	 * @param string               $method HTTP method.
	 * @param string               $path   Path below the namespace.
	 * @param array<string, mixed> $params Query (GET) or JSON body.
	 */
	private function call( string $method, string $path, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, self::NS . $path );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $params ) );
		}
		return rest_do_request( $request );
	}
}
