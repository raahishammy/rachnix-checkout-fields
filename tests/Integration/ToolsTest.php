<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Checkout\WooCoreFieldCatalog;
use Rachnix\CheckoutFields\Display\OrderFieldValues;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\I18n\StringTranslations;
use Rachnix\CheckoutFields\Plugin;
use Rachnix\CheckoutFields\Privacy\PrivacyIntegration;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Repository\SettingsRepository;
use Rachnix\CheckoutFields\Rest\ToolsController;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Security\RequestContext;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\ImportExportService;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\SettingsService;
use Rachnix\CheckoutFields\Service\SystemInfo;

/**
 * Step 10: signed export and import, reset everything, the wizard flag, privacy tools and
 * translations of store-owner texts. On the rxcftest_ tables and option.
 */
final class ToolsTest extends IntegrationTestCase {

	private const OPTION = 'rxcftest_settings';

	private static int $manager = 0;

	private ?\Closure $routes = null;

	private FieldRepository $repository;

	private FieldService $fields;

	private SettingsService $settings;

	private ImportExportService $transfer;

	public static function setUpBeforeClass(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
		self::$manager = self::create_test_user( 'shop_manager' );
	}

	public static function tearDownAfterClass(): void {
		wp_set_current_user( 0 );
		self::delete_test_user( self::$manager );
		delete_option( self::OPTION );
		self::reset_test_schema();
	}

	protected function setUp(): void {
		foreach ( array( self::tables()->fields(), self::tables()->audit_log() ) as $table ) {
			self::db()->query( self::db()->prepare( 'TRUNCATE TABLE %i', $table ) );
		}
		delete_transient( self::CACHE_KEY );
		delete_option( self::OPTION );

		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$audit            = new AuditLogRepository( self::db(), self::tables(), new RequestContext(), new Logger() );
		$this->repository = new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), new Logger() );
		$this->fields     = new FieldService( $this->repository, $audit, $registry, new PatternLibrary(), new WooCoreFieldCatalog() );
		$this->settings   = new SettingsService( new SettingsRepository( self::OPTION ), $audit );
		$this->transfer   = new ImportExportService( $this->fields, $this->settings, $audit );

		$controller   = new ToolsController( $this->transfer, $this->fields, $this->settings, new Logger(), 'rxcftest/v1' );
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
		remove_all_filters( 'salt' );
		remove_all_filters( 'wpml_translate_single_string' );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset for other tests.
		wp_set_current_user( 0 );
	}

	public function test_export_is_signed_and_tampering_is_detected(): void {
		$this->make_setup();
		$file = $this->transfer->export();

		$this->assertSame( ImportExportService::FORMAT, $file['format'] );
		$this->assertCount( 3, $file['fields'] );
		$this->assertTrue( $this->transfer->is_signed( $file ) );

		$file['fields'][0]['label'] = 'Changed by hand';
		$this->assertFalse( $this->transfer->is_signed( $file ) );
	}

	public function test_export_reset_import_restores_everything(): void {
		$this->make_setup();
		$before = $this->snapshot();
		$file   = $this->call( 'GET', '/export' )->get_data();

		$reset = $this->call( 'POST', '/reset-all', array( 'confirm' => true ) );
		$this->assertSame( 3, $reset->get_data()['deleted'] );
		$this->assertCount( 0, $this->fields->list() );
		$this->assertSame( 'theme', $this->settings->style()->mode->value );

		$imported = $this->call(
			'POST',
			'/import',
			array(
				'file'  => $file,
				'apply' => true,
			)
		);
		$this->assertSame( 200, $imported->get_status(), wp_json_encode( $imported->get_data() ) );
		$this->assertTrue( $imported->get_data()['signed'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( 'plugin', $this->settings->style()->mode->value );
	}

	public function test_a_file_from_another_site_needs_confirmation_then_imports_cleanly(): void {
		$this->make_setup();
		$before = $this->snapshot();
		$file   = $this->transfer->export();
		$this->call( 'POST', '/reset-all', array( 'confirm' => true ) );

		// Another site: different salts, so the signature no longer matches.
		add_filter( 'salt', static fn( string $salt ): string => 'other-site-' . $salt );

		$preview = $this->call( 'POST', '/import', array( 'file' => $file ) )->get_data();
		$this->assertFalse( $preview['signed'] );
		$this->assertSame( array( 'gift_note', 'slot' ), $preview['added'] );
		$this->assertCount( 0, $this->fields->list(), 'A preview changes nothing.' );

		$refused = $this->call(
			'POST',
			'/import',
			array(
				'file'  => $file,
				'apply' => true,
			)
		);
		$this->assertSame( 400, $refused->get_status() );
		$this->assertArrayHasKey( 'file', $refused->as_error()->get_error_data()['params'] );

		$confirmed = $this->call(
			'POST',
			'/import',
			array(
				'file'             => $file,
				'apply'            => true,
				'confirm_unsigned' => true,
			)
		);
		$this->assertSame( 200, $confirmed->get_status(), wp_json_encode( $confirmed->get_data() ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_import_replaces_the_current_setup_by_key(): void {
		$this->make_setup();
		$file = $this->transfer->export();

		// Change things after exporting: a renamed field, an extra field.
		$gift = $this->fields->list()->find_by_key( SectionKey::from_string( 'order' ), 'gift_note' );
		$this->fields->update( (string) $gift?->uuid, array( 'label' => 'Renamed' ), self::$manager );
		$this->fields->create( $this->definition( 'extra', 'text' ), self::$manager );

		$summary = $this->transfer->preview( $file, self::$manager );
		$this->assertSame( array(), $summary['added'] );
		$this->assertContains( 'gift_note', $summary['changed'] );
		$this->assertSame( array( 'extra' ), $summary['removed'] );

		$this->transfer->import( $file, self::$manager, false, false );
		$keys = array_map( static fn( Field $f ): string => $f->key, $this->fields->list()->to_list() );
		$this->assertNotContains( 'extra', $keys );
		$this->assertSame( 'Gift note', $this->fields->list()->find_by_key( SectionKey::from_string( 'order' ), 'gift_note' )?->label );
	}

	public function test_bad_files_are_refused_and_change_nothing(): void {
		$this->make_setup();
		$good   = $this->transfer->export();
		$before = $this->snapshot();

		$cases = array(
			'not ours'        => array( 'format' => 'something-else' ) + $good,
			'newer version'   => array( 'version' => 99 ) + $good,
			'fields not list' => array( 'fields' => array( 'a' => 1 ) ) + $good,
			'duplicate'       => array( 'fields' => array( $good['fields'][0], $good['fields'][0] ) ) + $good,
			'bad field'       => array( 'fields' => array( array( 'section' => 'order', 'key' => 'payment_method', 'type' => 'text' ) ) ) + $good, // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			'too many'        => array( 'fields' => array_fill( 0, ImportExportService::MAX_FIELDS + 1, $good['fields'][0] ) ) + $good,
		);
		foreach ( $cases as $name => $file ) {
			$response = $this->call(
				'POST',
				'/import',
				array(
					'file'             => $file,
					'apply'            => true,
					'confirm_unsigned' => true,
				)
			);
			$this->assertSame( 400, $response->get_status(), $name );
			$this->assertSame( $before, $this->snapshot(), "{$name}: nothing changed" );
		}
	}

	public function test_files_over_1_mb_are_refused(): void {
		$request = new \WP_REST_Request( 'POST', '/rxcftest/v1/import' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'file' => array( 'padding' => str_repeat( 'x', ImportExportService::MAX_BYTES ) ) ) ) );

		$this->assertSame( 413, rest_do_request( $request )->get_status() );
	}

	public function test_reset_all_needs_confirmation(): void {
		$this->make_setup();

		$this->assertSame( 400, $this->call( 'POST', '/reset-all', array( 'confirm' => false ) )->get_status() );
		$this->assertCount( 3, $this->fields->list() );
	}

	public function test_wizard_completion_is_remembered(): void {
		$this->assertFalse( $this->settings->wizard_completed() );
		$this->assertTrue( $this->call( 'POST', '/wizard/complete' )->get_data()['completed'] );
		$this->assertTrue( $this->settings->wizard_completed() );
	}

	public function test_delete_data_on_uninstall_is_off_by_default_and_needs_delete_plugins(): void {
		$this->assertFalse( $this->settings->delete_data_on_uninstall() );

		// A shop manager manages fields but cannot delete plugins, so cannot decide what deleting removes.
		$this->assertSame( 403, $this->call( 'POST', '/uninstall', array( 'delete_data' => true ) )->get_status() );
		$this->assertFalse( $this->settings->delete_data_on_uninstall() );

		$admin = self::create_test_user( 'administrator' );
		if ( is_multisite() ) {
			grant_super_admin( $admin ); // Only super admins can delete plugins on a network.
		}
		wp_set_current_user( $admin );
		try {
			$this->assertSame( array( 'delete_data' => true ), $this->call( 'POST', '/uninstall', array( 'delete_data' => true ) )->get_data() );
			$this->assertTrue( $this->settings->delete_data_on_uninstall() );
			$this->assertSame( array( 'delete_data' => false ), $this->call( 'POST', '/uninstall', array( 'delete_data' => false ) )->get_data() );
			$this->assertFalse( $this->settings->delete_data_on_uninstall() );
		} finally {
			wp_set_current_user( self::$manager );
			if ( is_multisite() ) {
				revoke_super_admin( $admin );
			}
			self::delete_test_user( $admin );
		}
	}

	public function test_system_info_helps_a_bug_report_without_identifying_the_store(): void {
		$this->make_setup();
		$info = new SystemInfo( $this->fields, new CheckoutDetector(), new SettingsRepository( self::OPTION ), self::VERSION_OPTION );
		$text = $info->as_text();

		$this->assertStringContainsString( 'Version: ' . Plugin::VERSION, $text );
		$this->assertStringContainsString( 'WordPress: ' . get_bloginfo( 'version' ), $text );
		$this->assertStringContainsString( 'PHP: ' . PHP_VERSION, $text );
		$this->assertStringContainsString( 'Style mode: plugin', $text );
		$this->assertStringContainsString( 'order (custom): gift_note [textarea], slot [select]', $text );
		$this->assertStringContainsString( 'billing (WooCommerce fields changed): billing_city', $text );

		// Nothing a store owner typed, and nothing that identifies the site or its people.
		foreach ( array( 'Gift note', 'Evening', 'Town', home_url(), wp_parse_url( home_url(), PHP_URL_HOST ), get_option( 'admin_email' ), get_bloginfo( 'name' ) ) as $private ) {
			if ( is_string( $private ) && '' !== $private ) {
				$this->assertStringNotContainsString( $private, $text );
			}
		}
	}

	public function test_privacy_export_and_erase_cover_both_checkouts(): void {
		$this->make_setup();
		$values  = new OrderFieldValues( $this->repository, $this->registry(), new OrderDataWriter() );
		$privacy = new PrivacyIntegration( $values, $this->repository, new OrderDataWriter() );

		$order = wc_create_order();
		$order->update_meta_data( '_rxcf_gift_note', 'Happy birthday' );
		$order->update_meta_data( '_wc_other/rachnix/slot', 'pm' );
		$order->update_meta_data( '_billing_unrelated', 'kept' );
		$order->save();

		try {
			$exported = $privacy->export_order( array(), $order );
			$this->assertContains(
				array(
					'name'  => 'Gift note',
					'value' => 'Happy birthday',
				),
				$exported
			);
			$this->assertContains(
				array(
					'name'  => 'Slot',
					'value' => 'Evening',
				),
				$exported
			);

			$privacy->erase_order( $order );
			$fresh = wc_get_order( $order->get_id() );
			$this->assertInstanceOf( \WC_Order::class, $fresh );
			$this->assertFalse( $fresh->meta_exists( '_rxcf_gift_note' ), 'Saved after erasing.' );
			$this->assertFalse( $fresh->meta_exists( '_wc_other/rachnix/slot' ) );
			$this->assertSame( 'kept', $fresh->get_meta( '_billing_unrelated' ), 'Other data untouched.' );
		} finally {
			$order->delete( true );
		}

		// A real customer account, as WooCommerce's eraser passes one.
		$user_id  = self::create_test_user( 'customer' );
		$customer = new \WC_Customer( $user_id );
		$customer->update_meta_data( '_wc_other/rachnix/gift_note', 'on profile' );
		$customer->save();
		try {
			$this->assertSame( 'on profile', $privacy->export_customer( array(), $customer )[0]['value'] );
			$response = $privacy->erase_customer(
				array(
					'messages'      => array(),
					'items_removed' => false,
				),
				$customer
			);
			$this->assertTrue( $response['items_removed'] );
			$this->assertFalse( ( new \WC_Customer( $user_id ) )->meta_exists( '_wc_other/rachnix/gift_note' ), 'Saved after erasing.' );
		} finally {
			self::delete_test_user( $user_id );
		}
	}

	public function test_store_owner_texts_are_translated_with_wpml(): void {
		$field = new Field(
			wp_generate_uuid4(),
			SectionKey::from_string( 'order' ),
			'slot',
			'select',
			'Slot',
			'Pick one',
			new FieldSettings( options: array( new FieldOption( 'pm', 'Evening' ) ) )
		);
		$off   = new StringTranslations( $this->repository, 'none' );
		$this->assertSame( $field, $off->field( $field ), 'Without WPML or Polylang nothing changes.' );

		add_filter(
			'wpml_translate_single_string',
			static fn( string $value, string $context, string $name ): string => StringTranslations::CONTEXT === $context ? "[{$name}] {$value}" : $value,
			10,
			3
		);
		$wpml       = new StringTranslations( $this->repository, 'wpml' );
		$translated = $wpml->field( $field );

		$this->assertSame( '[order.slot.label] Slot', $translated->label );
		$this->assertSame( '[order.slot.placeholder] Pick one', $translated->placeholder );
		$this->assertSame( '[order.slot.option.pm] Evening', $translated->settings->options[0]->label );
		$this->assertSame( 'pm', $translated->settings->options[0]->value, 'Saved values never change.' );
		$this->assertSame(
			array(
				'order.slot.label'       => 'Slot',
				'order.slot.placeholder' => 'Pick one',
				'order.slot.option.pm'   => 'Evening',
			),
			$wpml->strings( $field )
		);
	}

	/**
	 * Two custom fields, one WooCommerce field override, and Plugin style.
	 */
	private function make_setup(): void {
		$this->fields->create( $this->definition( 'gift_note', 'textarea', 'Gift note' ), self::$manager );
		$this->fields->create(
			$this->definition( 'slot', 'select', 'Slot' ) + array(
				'settings' => array(
					'options' => array(
						array(
							'value' => 'pm',
							'label' => 'Evening',
						),
					),
				),
			),
			self::$manager
		);
		$this->fields->customize_core( SectionKey::from_string( 'billing' ), 'billing_city', array( 'label' => 'Town' ), self::$manager );
		$this->settings->update_style( array( 'mode' => 'plugin' ), self::$manager, false );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function definition( string $key, string $type, string $label = 'Label' ): array {
		return array(
			'section' => 'order',
			'key'     => $key,
			'type'    => $type,
			'label'   => $label,
		);
	}

	/**
	 * The setup without ids and timestamps, which legitimately change on import.
	 *
	 * @return array<string, mixed>
	 */
	private function snapshot(): array {
		$fields = array();
		foreach ( $this->fields->list() as $field ) {
			$data = $field->to_array();
			unset( $data['uuid'] );
			$fields[ $field->section->value . ':' . $field->key ] = $data;
		}
		ksort( $fields );
		return array(
			'fields' => $fields,
			'style'  => $this->settings->style()->to_array(),
		);
	}

	private function registry(): FieldTypeRegistry {
		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		return $registry;
	}

	/**
	 * @param string               $method Method.
	 * @param string               $path   Path below /rxcftest/v1.
	 * @param array<string, mixed> $body   JSON body.
	 */
	private function call( string $method, string $path, array $body = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rxcftest/v1' . $path );
		if ( 'POST' === $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}
}
