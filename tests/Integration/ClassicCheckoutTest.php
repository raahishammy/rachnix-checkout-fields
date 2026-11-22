<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Checkout\Classic\ClassicCheckoutIntegration;
use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\Domain\Width;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\Sanitizer;
use Rachnix\CheckoutFields\Service\ValidationService;

/**
 * The classic checkout integration against WooCommerce's real field definitions
 * and woocommerce_form_field(). Field definitions live in the rxcftest_ tables.
 */
final class ClassicCheckoutTest extends IntegrationTestCase {

	private FieldRepository $repository;

	private ClassicCheckoutIntegration $integration;

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

		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$this->repository  = new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), new Logger() );
		$this->integration = new ClassicCheckoutIntegration(
			$this->repository,
			$registry,
			new Sanitizer( $registry ),
			new ValidationService( $registry, new PatternLibrary(), new Logger() ),
			new OrderDataWriter()
		);
		$_POST             = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	public function test_custom_fields_join_their_fieldset_with_woocommerce_arguments(): void {
		$this->save( 'billing', 'vat_number', 'text', priority: 35, label: 'VAT number', placeholder: 'GB123', settings: new FieldSettings( required: true, css_classes: array( 'my-vat' ), validation: array( new ValidationRule( ValidationRuleType::Length, max: 20.0 ) ) ) );
		$this->save( 'order', 'delivery_slot', 'select', settings: new FieldSettings( options: array( new FieldOption( 'am', 'Morning' ), new FieldOption( 'pm', 'Evening' ) ) ) );
		$this->save( 'order', 'floor', 'number', settings: new FieldSettings( validation: array( new ValidationRule( ValidationRuleType::NumberRange, 0.0, 50.0 ) ) ) );
		$this->save( 'billing', 'campaign', 'hidden', label: 'Campaign', settings: new FieldSettings( default_value: 'autumn' ) );
		$this->save( 'contact', 'newsletter', 'checkbox', label: 'Send me news' );

		$fields = $this->integration->filter_fields( $this->woocommerce_fields() );

		$vat = $fields['billing']['rxcf_vat_number'];
		$this->assertSame( 'text', $vat['type'] );
		$this->assertSame( 'VAT number', $vat['label'] );
		$this->assertTrue( $vat['required'] );
		$this->assertSame( 35, $vat['priority'] );
		$this->assertContains( 'my-vat', $vat['class'] );
		$this->assertContains( 'form-row-wide', $vat['class'] );
		$this->assertSame( '20', $vat['custom_attributes']['maxlength'] );

		$slot = $fields['order']['rxcf_delivery_slot'];
		$this->assertSame(
			array(
				''   => 'Choose an option',
				'am' => 'Morning',
				'pm' => 'Evening',
			),
			$slot['options']
		);

		$this->assertSame( '0', $fields['order']['rxcf_floor']['custom_attributes']['min'] );
		$this->assertSame( 'any', $fields['order']['rxcf_floor']['custom_attributes']['step'] );
		$this->assertSame( '', $fields['billing']['rxcf_campaign']['label'], 'Hidden fields show no label.' );
		$this->assertSame( 'autumn', $fields['billing']['rxcf_campaign']['default'] );
		$this->assertArrayHasKey( 'rxcf_newsletter', $fields['billing'], 'Contact fields join billing on the classic checkout.' );
		$this->assertArrayHasKey( 'billing_first_name', $fields['billing'], 'Core fields are kept.' );
	}

	public function test_disabled_and_unknown_type_fields_are_left_out(): void {
		$this->save( 'billing', 'off', 'text', enabled: false );
		$this->save( 'billing', 'pro_only', 'color' );

		$fields = $this->integration->filter_fields( $this->woocommerce_fields() );

		$this->assertArrayNotHasKey( 'rxcf_off', $fields['billing'] );
		$this->assertArrayNotHasKey( 'rxcf_pro_only', $fields['billing'] );
	}

	public function test_core_fields_can_be_hidden_relabelled_and_moved(): void {
		$this->assertArrayHasKey( 'billing_city', $this->woocommerce_fields()['billing'], 'Precondition: WooCommerce shows the field.' );
		$this->save_core( 'billing', 'billing_city', enabled: false );
		$this->save_core( 'billing', 'billing_phone', label: 'Mobile', priority: 5, settings: new FieldSettings( required: true, width: Width::Full ) );
		$this->save_core( 'billing', 'billing_last_name', label: '', settings: new FieldSettings( required: false, width: Width::Half ) );

		$fields = $this->integration->filter_fields( $this->woocommerce_fields() );

		$this->assertArrayNotHasKey( 'billing_city', $fields['billing'] );
		$this->assertSame( 'Mobile', $fields['billing']['billing_phone']['label'] );
		$this->assertSame( 5, $fields['billing']['billing_phone']['priority'] );
		$this->assertTrue( $fields['billing']['billing_phone']['required'] );
		$this->assertContains( 'form-row-wide', $fields['billing']['billing_phone']['class'] );
		$this->assertNotSame( '', $fields['billing']['billing_last_name']['label'], 'Empty label keeps WooCommerce text.' );
		$this->assertFalse( $fields['billing']['billing_last_name']['required'] );
	}

	public function test_half_width_fields_pair_up(): void {
		$half = new FieldSettings( width: Width::Half );
		$this->save( 'order', 'one', 'text', priority: 20, settings: $half );
		$this->save( 'order', 'two', 'text', priority: 30, settings: $half );
		$this->save( 'order', 'three', 'text', priority: 40, settings: $half );

		$order = $this->integration->filter_fields( $this->woocommerce_fields() )['order'];

		$this->assertContains( 'form-row-first', $order['rxcf_one']['class'] );
		$this->assertContains( 'form-row-last', $order['rxcf_two']['class'] );
		$this->assertContains( 'form-row-first', $order['rxcf_three']['class'] );
	}

	public function test_rendered_html_escapes_hostile_definitions(): void {
		// Built directly, bypassing FieldService's sanitising, to prove output is escaped as well.
		$this->save(
			'order',
			'evil',
			'select',
			label: 'Pick <script>alert(1)</script>',
			settings: new FieldSettings( options: array( new FieldOption( '"><img src=x onerror=alert(2)>', '<b onmouseover=alert(3)>x</b>' ) ) )
		);

		$args = $this->integration->filter_fields( $this->woocommerce_fields() )['order']['rxcf_evil'];
		$html = woocommerce_form_field( 'rxcf_evil', array_merge( $args, array( 'return' => true ) ) );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<b onmouseover', $html );
		$this->assertStringContainsString( 'name="rxcf_evil"', $html );
	}

	public function test_every_free_type_renders(): void {
		foreach ( FieldTypeRegistry::FREE_TYPES as $class_name ) {
			$slug = ( new $class_name() )->slug();
			$this->save( 'order', "type_{$slug}", $slug, label: "Type {$slug}", settings: new FieldSettings( options: array( new FieldOption( 'a', 'A' ) ) ) );
		}

		$order = $this->integration->filter_fields( $this->woocommerce_fields() )['order'];

		foreach ( FieldTypeRegistry::FREE_TYPES as $class_name ) {
			$slug = ( new $class_name() )->slug();
			$html = woocommerce_form_field( "rxcf_type_{$slug}", array_merge( $order[ "rxcf_type_{$slug}" ], array( 'return' => true ) ) );
			$this->assertStringContainsString( "name=\"rxcf_type_{$slug}", $html, $slug );
		}
	}

	public function test_radio_options_do_not_repeat_optional_or_required(): void {
		add_filter( 'woocommerce_form_field_radio', array( $this->integration, 'tidy_radio_options' ), 10, 2 );
		try {
			foreach ( array( false, true ) as $required ) {
				$args   = array(
					'type'     => 'radio',
					'label'    => 'Wrapping',
					'required' => $required,
					'options'  => array(
						'paper' => 'Paper',
						'box'   => 'Box',
					),
					'return'   => true,
				);
				$ours   = (string) woocommerce_form_field( 'rxcf_wrap', $args );
				$theirs = (string) woocommerce_form_field( 'other_plugin_wrap', $args );
				$marker = $required ? 'class="required"' : 'class="optional"';

				$this->assertMatchesRegularExpression( '#class="radio\b[^"]*"[^>]*>Paper</label>#', $ours );
				$this->assertMatchesRegularExpression( '#class="radio\b[^"]*"[^>]*>Box</label>#', $ours );
				$this->assertSame( 1, substr_count( $ours, $marker ), 'Only the field label keeps the marker.' );
				$this->assertSame( 3, substr_count( $theirs, $marker ), 'Fields of other plugins are left alone.' );
			}
		} finally {
			remove_filter( 'woocommerce_form_field_radio', array( $this->integration, 'tidy_radio_options' ), 10 );
		}
	}

	public function test_validation_adds_format_errors_but_leaves_required_to_woocommerce(): void {
		$this->save( 'billing', 'floor', 'number', label: 'Floor', settings: new FieldSettings( validation: array( new ValidationRule( ValidationRuleType::NumberRange, 0.0, 10.0 ) ) ) );
		$this->save( 'billing', 'alt_email', 'email', label: 'Other email' );
		$this->save( 'billing', 'must', 'text', label: 'Must', settings: new FieldSettings( required: true ) );
		$this->save( 'shipping', 'gate_code', 'number', label: 'Gate code' );

		$_POST  = array(
			'rxcf_floor'     => '99',
			'rxcf_alt_email' => 'not-an-email',
			'rxcf_must'      => '',
			'rxcf_gate_code' => 'abc',
		);
		$errors = new \WP_Error();
		$this->integration->validate( array( 'ship_to_different_address' => false ), $errors );

		$this->assertEqualsCanonicalizing(
			array( 'Floor must be between 0 and 10.', 'Other email is not a valid email address.' ),
			$errors->get_error_messages( 'validation' ),
			'No required error (WooCommerce adds it), no shipping error (not shipping elsewhere).'
		);
		$this->assertContains( array( 'id' => 'rxcf_floor' ), $errors->get_all_error_data( 'validation' ), 'Error points WooCommerce at the field.' );
	}

	public function test_values_are_saved_on_the_order_sanitised(): void {
		$this->save( 'billing', 'vat_number', 'text' );
		$this->save( 'order', 'note', 'textarea' );
		$this->save( 'order', 'gift', 'checkbox' );
		$this->save( 'order', 'empty_one', 'text' );
		$this->save( 'shipping', 'gate', 'text' );

		$_POST = array(
			'rxcf_vat_number' => 'GB 123 <script>alert(1)</script>',
			'rxcf_note'       => "Line one\nLine two",
			'rxcf_gift'       => '1',
			'rxcf_empty_one'  => '',
			'rxcf_gate'       => '4321',
		);
		$order = new \WC_Order();
		$this->integration->save( $order, array( 'ship_to_different_address' => false ) );

		$this->assertSame( 'GB 123', $order->get_meta( '_rxcf_vat_number' ) );
		$this->assertSame( "Line one\nLine two", $order->get_meta( '_rxcf_note' ) );
		$this->assertSame( '1', $order->get_meta( '_rxcf_gift' ) );
		$this->assertFalse( $order->meta_exists( '_rxcf_empty_one' ), 'Empty values are not stored.' );
		$this->assertFalse( $order->meta_exists( '_rxcf_gate' ), 'Shipping fields only when shipping elsewhere.' );
		$this->assertFalse( $order->meta_exists( '_billing_vat_number' ), 'WooCommerce did not store a copy.' );
	}

	public function test_core_rows_are_never_validated_or_saved_by_us(): void {
		$this->save_core( 'billing', 'billing_phone', label: 'Mobile' );
		$_POST = array( 'rxcf_billing_phone' => 'x' );

		$errors = new \WP_Error();
		$this->integration->validate( array(), $errors );
		$order = new \WC_Order();
		$this->integration->save( $order, array() );

		$this->assertFalse( $errors->has_errors() );
		$this->assertFalse( $order->meta_exists( '_rxcf_billing_phone' ) );
	}

	/**
	 * WooCommerce's checkout fields as WooCommerce builds them, before any filter.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function woocommerce_fields(): array {
		$country = WC()->countries->get_base_country();
		return array(
			'billing'  => WC()->countries->get_address_fields( $country, 'billing_' ),
			'shipping' => WC()->countries->get_address_fields( $country, 'shipping_' ),
			'account'  => array(),
			'order'    => array(
				'order_comments' => array(
					'type'  => 'textarea',
					'label' => 'Order notes',
				),
			),
		);
	}

	private function save( string $section, string $key, string $type, int $priority = 100, string $label = 'Label', string $placeholder = '', FieldSettings $settings = new FieldSettings(), bool $enabled = true ): void {
		$this->repository->save( new Field( wp_generate_uuid4(), SectionKey::from_string( $section ), $key, $type, $label, $placeholder, $settings, $priority, $enabled ) );
	}

	private function save_core( string $section, string $key, string $label = '', int $priority = 10, FieldSettings $settings = new FieldSettings(), bool $enabled = true ): void {
		$this->repository->save( new Field( wp_generate_uuid4(), SectionKey::core( Section::from( $section ) ), $key, FieldService::CORE_TYPE, $label, '', $settings, $priority, $enabled, is_core: true ) );
	}
}
