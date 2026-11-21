<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use Rachnix\CheckoutFields\Admin\BlockCheckoutNotice;
use Rachnix\CheckoutFields\Checkout\Blocks\BlockFieldSupport;
use Rachnix\CheckoutFields\Checkout\Blocks\BlocksCheckoutIntegration;
use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\Sanitizer;
use Rachnix\CheckoutFields\Service\ValidationService;

/**
 * Block checkout integration against WooCommerce's real Additional Checkout Fields API.
 * Field definitions live in the rxcftest_ tables; registered ids use a per-run suffix.
 */
final class BlocksCheckoutTest extends IntegrationTestCase {

	private FieldRepository $repository;

	private FieldTypeRegistry $registry;

	private CheckoutDetector $detector;

	public static function setUpBeforeClass(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
	}

	public static function tearDownAfterClass(): void {
		self::reset_test_schema();
	}

	protected function setUp(): void {
		self::db()->query( self::db()->prepare( 'TRUNCATE TABLE %i', self::tables()->fields() ) );
		delete_transient( self::CACHE_KEY );

		$this->registry = new FieldTypeRegistry();
		$this->registry->register_free_types();
		$this->repository = new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), new Logger() );
		$this->detector   = new CheckoutDetector();
	}

	protected function tearDown(): void {
		remove_all_filters( 'rxcf_register_block_checkout_fields' );
		$this->detector->clear(); // Never leave a forced answer in the real site's cache.
	}

	public function test_supported_types_get_native_options(): void {
		$integration = $this->integration( date: true );

		$text = $integration->options_for( $this->field( 'billing', 'vat', 'text', label: 'VAT', settings: new FieldSettings( required: true, validation: array( new ValidationRule( ValidationRuleType::Length, max: 20.0 ) ) ) ) );
		$this->assertSame( 'rachnix/vat', $text['id'] );
		$this->assertSame( 'address', $text['location'] );
		$this->assertSame( 'text', $text['type'] );
		$this->assertTrue( $text['required'] );
		$this->assertSame( array( 'maxLength' => 20 ), $text['attributes'] );
		$this->assertIsCallable( $text['sanitize_callback'] );
		$this->assertIsCallable( $text['validate_callback'] );

		$email = $integration->options_for( $this->field( 'contact', 'alt_email', 'email' ) );
		$this->assertSame( 'contact', $email['location'] );
		$this->assertSame( 'text', $email['type'] );
		$this->assertSame( 'email', $email['attributes']['autocomplete'] );

		$select = $integration->options_for( $this->field( 'order', 'slot', 'select', placeholder: 'Pick one', settings: new FieldSettings( options: array( new FieldOption( 'am', 'Morning' ) ) ) ) );
		$this->assertSame( 'order', $select['location'] );
		$this->assertSame(
			array(
				array(
					'value' => 'am',
					'label' => 'Morning',
				),
			),
			$select['options']
		);
		$this->assertSame( 'Pick one', $select['placeholder'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $select, 'WooCommerce sanitises selects.' );

		$checkbox = $integration->options_for( $this->field( 'order', 'gift', 'checkbox' ) );
		$this->assertSame( 'checkbox', $checkbox['type'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $checkbox, 'Checkbox values must stay booleans.' );

		$this->assertSame( 'date', $integration->options_for( $this->field( 'order', 'day', 'date' ) )['type'] );
	}

	public function test_unsupported_fields_are_not_registered(): void {
		$integration = $this->integration( date: false );

		foreach ( array( 'textarea', 'number', 'password', 'radio', 'hidden', 'date' ) as $type ) {
			$this->assertNull( $integration->options_for( $this->field( 'order', "f_{$type}", $type ) ), $type );
		}
		$this->assertNull( $integration->options_for( $this->field( 'order', 'off', 'text', enabled: false ) ), 'disabled' );
		$this->assertNull( $integration->options_for( $this->field( 'order', 'pro', 'color' ) ), 'unknown type' );
		$this->assertNull( $integration->options_for( $this->core( 'billing_phone' ) ), 'core override' );
	}

	public function test_validate_callback_reports_every_kind_of_error(): void {
		$integration = $this->integration( date: true );

		$vat = $this->field( 'billing', 'vat', 'text', label: 'VAT', settings: new FieldSettings( required: true, validation: array( new ValidationRule( ValidationRuleType::Pattern, pattern_id: 'digits' ) ) ) );
		$this->assertSame( array( 'VAT is a required field.' ), $this->messages( $integration->validate( $vat, '' ) ) );
		$this->assertSame( array( 'VAT is not in the expected format, for example 123456.' ), $this->messages( $integration->validate( $vat, 'abc' ) ) );
		$this->assertTrue( $integration->validate( $vat, '123' ) );

		$email = $this->field( 'contact', 'alt_email', 'email', label: 'Other email' );
		$this->assertSame( array( 'Other email is not a valid email address.' ), $this->messages( $integration->validate( $email, 'nope' ) ) );

		$gift = $this->field( 'order', 'gift', 'checkbox', label: 'Terms', settings: new FieldSettings( required: true ) );
		$this->assertSame( array( 'Terms is a required field.' ), $this->messages( $integration->validate( $gift, false ) ) );
		$this->assertTrue( $integration->validate( $gift, true ), 'The block sends booleans.' );

		$day = $this->field( 'order', 'day', 'date', label: 'Day' );
		$this->assertSame( array( 'Day must be a valid date.' ), $this->messages( $integration->validate( $day, '2026-02-30' ) ) );
	}

	public function test_text_sanitiser_strips_markup(): void {
		$options = $this->integration( date: false )->options_for( $this->field( 'order', 'note_line', 'text' ) );

		$this->assertSame( 'Hello', ( $options['sanitize_callback'] )( 'Hello<script>alert(1)</script>' ) );
		$this->assertSame( '', ( $options['sanitize_callback'] )( array( 'x' ) ) );
	}

	public function test_fields_register_with_woocommerce_only_when_the_block_checkout_is_used(): void {
		$suffix = strtolower( wp_generate_password( 6, false ) );
		$this->repository->save( $this->field( 'order', "e2e_a_{$suffix}", 'text', label: 'A' ) );
		$this->repository->save( $this->field( 'order', "e2e_b_{$suffix}", 'textarea', label: 'B' ) );
		$controller = Package::container()->get( CheckoutFields::class );

		add_filter( 'rxcf_register_block_checkout_fields', '__return_false' );
		$this->integration( date: false )->register_fields();
		$this->assertFalse( $controller->is_field( "rachnix/e2e_a_{$suffix}" ), 'Classic checkout: nothing registered.' );

		remove_all_filters( 'rxcf_register_block_checkout_fields' );
		add_filter( 'rxcf_register_block_checkout_fields', '__return_true' );
		$this->integration( date: false )->register_fields();
		$this->assertTrue( $controller->is_field( "rachnix/e2e_a_{$suffix}" ) );
		$this->assertFalse( $controller->is_field( "rachnix/e2e_b_{$suffix}" ), 'Textarea is not available on blocks.' );

		// WooCommerce runs our validate callback.
		$field  = $controller->get_additional_fields()[ "rachnix/e2e_a_{$suffix}" ];
		$errors = $controller->validate_field( $field, str_repeat( 'x', 300 ) );
		$this->assertSame( array( 'A must be at most 255 characters long.' ), $errors->get_error_messages() );

		__internal_woocommerce_blocks_deregister_checkout_field( "rachnix/e2e_a_{$suffix}" );
	}

	public function test_notice_lists_fields_the_block_checkout_cannot_show(): void {
		$this->repository->save( $this->field( 'order', 'note', 'textarea', label: 'Delivery note' ) );
		$this->repository->save( $this->field( 'order', 'floor', 'number', label: 'Floor' ) );
		$this->repository->save( $this->field( 'order', 'slot', 'select', label: 'Slot' ) );
		$this->repository->save( $this->field( 'order', 'off', 'radio', label: 'Disabled radio', enabled: false ) );

		set_transient( 'rxcf_uses_block_checkout', 'yes', MINUTE_IN_SECONDS );
		$html = $this->notice( date: false )->html();

		$this->assertStringContainsString( 'Delivery note (Text area)', $html );
		$this->assertStringContainsString( 'Floor (Number)', $html );
		$this->assertStringNotContainsString( 'Slot', $html, 'Shown on blocks.' );
		$this->assertStringNotContainsString( 'Disabled radio', $html );
		$this->assertStringNotContainsString( 'classic checkout only', $html, 'No core overrides yet.' );

		$this->repository->save( $this->core( 'billing_company' ) );
		$this->assertStringContainsString( 'classic checkout only', $this->notice( date: false )->html() );
	}

	public function test_no_notice_on_the_classic_checkout(): void {
		$this->repository->save( $this->field( 'order', 'note', 'textarea', label: 'Delivery note' ) );
		set_transient( 'rxcf_uses_block_checkout', 'no', MINUTE_IN_SECONDS );

		$this->assertSame( '', $this->notice( date: false )->html() );
	}

	public function test_notice_escapes_field_labels(): void {
		// Stored directly, bypassing FieldService's sanitising, to prove output is escaped.
		$this->repository->save( $this->field( 'order', 'evil', 'textarea', label: '<img src=x onerror=alert(1)>' ) );
		set_transient( 'rxcf_uses_block_checkout', 'yes', MINUTE_IN_SECONDS );

		$html = $this->notice( date: false )->html();
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( '&lt;img', $html );
	}

	public function test_detector_cache_clears_when_a_page_is_saved(): void {
		set_transient( 'rxcf_uses_block_checkout', 'yes', MINUTE_IN_SECONDS );
		$this->assertTrue( $this->detector->uses_block_checkout() );

		$this->detector->register();
		foreach ( array( 'save_post', 'deleted_post', 'switch_theme', 'update_option_woocommerce_checkout_page_id' ) as $hook ) {
			$this->assertNotFalse( has_action( $hook, array( $this->detector, 'clear' ) ), $hook );
			remove_action( $hook, array( $this->detector, 'clear' ) );
		}

		// What those hooks call (firing save_post here would also run other plugins' listeners).
		$this->detector->clear();
		$this->assertFalse( get_transient( 'rxcf_uses_block_checkout' ), 'The cached answer is gone.' );
	}

	private function integration( bool $date ): BlocksCheckoutIntegration {
		return new BlocksCheckoutIntegration(
			$this->repository,
			$this->registry,
			new BlockFieldSupport( date_supported: $date ),
			$this->detector,
			new Sanitizer( $this->registry ),
			new ValidationService( $this->registry, new PatternLibrary(), new Logger() ),
			new Logger()
		);
	}

	private function notice( bool $date ): BlockCheckoutNotice {
		return new BlockCheckoutNotice( $this->repository, $this->registry, new BlockFieldSupport( date_supported: $date ), new CheckoutDetector() );
	}

	private function field( string $section, string $key, string $type, string $label = 'Label', string $placeholder = '', FieldSettings $settings = new FieldSettings(), bool $enabled = true ): Field {
		return new Field( wp_generate_uuid4(), SectionKey::from_string( $section ), $key, $type, $label, $placeholder, $settings, enabled: $enabled );
	}

	private function core( string $key ): Field {
		return new Field( wp_generate_uuid4(), SectionKey::from_string( 'billing' ), $key, FieldService::CORE_TYPE, is_core: true );
	}

	/**
	 * @param bool|\WP_Error $result Validate callback result.
	 * @return list<string>
	 */
	private function messages( bool|\WP_Error $result ): array {
		return $result instanceof \WP_Error ? $result->get_error_messages() : array();
	}
}
