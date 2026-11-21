<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Display\BlockFieldVisibility;
use Rachnix\CheckoutFields\Display\CustomerDisplay;
use Rachnix\CheckoutFields\Display\EmailDisplay;
use Rachnix\CheckoutFields\Display\OrderAdminDisplay;
use Rachnix\CheckoutFields\Display\OrderFieldValues;
use Rachnix\CheckoutFields\Domain\DisplayFlags;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Rest\OrderFieldsRest;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;

/**
 * Step 9: where values show, for classic-checkout values (_rxcf_ meta) and block-checkout
 * values (WooCommerce's _wc_* meta). Field definitions live in the rxcftest_ tables.
 */
final class DisplayTest extends IntegrationTestCase {

	private FieldRepository $repository;

	private OrderFieldValues $values;

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
		$this->repository = new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), new Logger() );
		$this->values     = new OrderFieldValues( $this->repository, $registry, new OrderDataWriter() );
	}

	protected function tearDown(): void {
		unset( $_GET['key'] );
		wp_set_current_user( 0 );
	}

	public function test_values_are_read_from_either_checkout(): void {
		$this->save( 'order', 'gift_note', 'text', 'Gift note' );
		$this->save( 'billing', 'vat', 'text', 'VAT' );
		$this->save( 'order', 'unused', 'text', 'Unused' );
		$this->save( 'order', 'retired', 'text', 'Retired', enabled: false );
		$this->repository->save( new Field( wp_generate_uuid4(), SectionKey::from_string( 'billing' ), 'billing_phone', FieldService::CORE_TYPE, is_core: true ) );

		$order = new \WC_Order();
		$order->update_meta_data( '_rxcf_gift_note', 'Happy birthday' );            // Classic.
		$order->update_meta_data( '_wc_billing/rachnix/vat', 'GB123' );            // Block, billing address.
		$order->update_meta_data( '_wc_shipping/rachnix/vat', 'IGNORED' );         // Same field, other address.
		$order->update_meta_data( '_rxcf_retired', 'Still on old orders' );

		$found = array();
		foreach ( $this->values->for_order( $order ) as $value ) {
			$found[ $value->field->key ] = array( $value->value, $value->source );
		}

		$this->assertSame(
			array(
				'gift_note' => array( 'Happy birthday', 'classic' ),
				'retired'   => array( 'Still on old orders', 'classic' ),
				'vat'       => array( 'GB123', 'blocks' ),
			),
			$this->sorted( $found )
		);
	}

	public function test_values_are_formatted_by_type_and_audience(): void {
		$this->save( 'order', 'slot', 'select', 'Slot', settings: new FieldSettings( options: array( new FieldOption( 'pm', 'Evening' ) ) ) );
		$this->save( 'order', 'gift', 'checkbox', 'Gift' );
		$this->save( 'order', 'day', 'date', 'Day' );
		$this->save( 'order', 'pin', 'password', 'PIN' );
		$this->save( 'order', 'source', 'hidden', 'Source' );

		$order = new \WC_Order();
		foreach ( array(
			'slot'   => 'pm',
			'gift'   => '1',
			'day'    => '2026-12-24',
			'pin'    => '1234',
			'source' => 'autumn',
		) as $key => $raw ) {
			$order->update_meta_data( "_rxcf_{$key}", $raw );
		}

		$shown = array();
		foreach ( $this->values->for_order( $order ) as $value ) {
			$shown[ $value->field->key ] = array(
				'customer' => $this->values->display( $value, false ),
				'store'    => $this->values->display( $value, true ),
				'unmasked' => $this->values->display( $value, true, true ),
			);
		}

		$this->assertSame( 'Evening', $shown['slot']['customer'], 'Choice label, not value.' );
		$this->assertSame( 'Yes', $shown['gift']['customer'] );
		$this->assertSame( wp_date( (string) get_option( 'date_format' ), ( new \DateTimeImmutable( '2026-12-24', wp_timezone() ) )->getTimestamp() ), $shown['day']['customer'] );
		$this->assertSame( '••••', $shown['pin']['customer'] );
		$this->assertSame( '••••', $shown['pin']['store'], 'Masked in emails to the store too.' );
		$this->assertSame( '1234', $shown['pin']['unmasked'], 'Real value on the order screen and API.' );
		$this->assertNull( $shown['source']['customer'], 'Hidden fields are for the store only.' );
		$this->assertSame( 'autumn', $shown['source']['store'] );
	}

	public function test_admin_order_box_shows_classic_values_and_escapes(): void {
		$this->save( 'order', 'note', 'textarea', 'Note <script>' );
		$this->save( 'order', 'private_note', 'text', 'Private', display: new DisplayFlags( order_admin: false ) );
		$this->save( 'billing', 'vat', 'text', 'VAT' );

		$order = new \WC_Order();
		$order->update_meta_data( '_rxcf_note', "Line one\n<img src=x onerror=alert(1)>" );
		$order->update_meta_data( '_rxcf_private_note', 'not here' );
		$order->update_meta_data( '_wc_billing/rachnix/vat', 'GB123' );

		$html = ( new OrderAdminDisplay( $this->values, $this->repository ) )->box_html( $order );

		$this->assertStringContainsString( 'Note &lt;script&gt;', $html );
		$this->assertStringContainsString( 'Line one<br />', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'not here', $html, 'Order screen switch is off.' );
		$this->assertStringNotContainsString( 'GB123', $html, 'WooCommerce shows block values on this screen itself.' );
	}

	public function test_orders_list_columns_for_both_storage_types(): void {
		$list = new DisplayFlags( order_list: true );
		$this->save( 'order', 'gift_note', 'text', 'Gift note', display: $list );
		$this->save( 'billing', 'vat', 'text', 'VAT', display: $list );
		$this->save( 'order', 'zz_third', 'text', 'Third', display: $list ); // Equal priority sorts by key: this one is third.
		$admin = new OrderAdminDisplay( $this->values, $this->repository );

		$columns = $admin->add_columns(
			array(
				'order_number' => 'Order',
				'wc_actions'   => 'Actions',
			)
		);
		$this->assertCount( 4, $columns, 'At most two of our columns.' );
		$this->assertSame( 'wc_actions', array_key_last( $columns ), 'Ours come before the actions column.' );

		$order = new \WC_Order();
		$order->update_meta_data( '_rxcf_gift_note', str_repeat( 'a', 80 ) );
		$order->update_meta_data( '_wc_billing/rachnix/vat', 'GB123' );

		$keys = array_keys( $columns );
		$this->assertSame( 60, mb_strlen( html_entity_decode( $admin->cell_html( $keys[1], $order ) ) ), 'Long values are shortened.' );
		$this->assertContains( 'GB123', array( $admin->cell_html( $keys[1], $order ), $admin->cell_html( $keys[2], $order ) ) );
		$this->assertSame( '', $admin->cell_html( 'order_number', $order ) );

		$admin->register();
		$this->assertNotFalse( has_filter( 'manage_woocommerce_page_wc-orders_columns', array( $admin, 'add_columns' ) ), 'HPOS list.' );
		$this->assertNotFalse( has_filter( 'manage_edit-shop_order_columns', array( $admin, 'add_columns' ) ), 'Legacy list.' );
		$this->unregister( $admin );
	}

	public function test_customer_pages_follow_their_switches_and_hide_store_only_fields(): void {
		$this->save( 'order', 'gift_note', 'text', 'Gift note' );
		$this->save( 'order', 'account_only', 'text', 'Account only', display: new DisplayFlags( thank_you: false ) );
		$this->save( 'order', 'source', 'hidden', 'Source' );
		$this->save( 'order', 'pin', 'password', 'PIN' );
		$this->save( 'order', 'block_value', 'text', 'Block value' );

		$order = new \WC_Order();
		$order->update_meta_data( '_rxcf_gift_note', 'Happy birthday' );
		$order->update_meta_data( '_rxcf_account_only', 'Visible later' );
		$order->update_meta_data( '_rxcf_source', 'autumn' );
		$order->update_meta_data( '_rxcf_pin', '1234' );
		$order->update_meta_data( '_wc_other/rachnix/block_value', 'from blocks' );
		$display = new CustomerDisplay( $this->values );

		$thanks = $display->table_html( $order, 'thank_you' );
		$this->assertStringContainsString( 'Happy birthday', $thanks );
		$this->assertStringNotContainsString( 'Visible later', $thanks );
		$this->assertStringNotContainsString( 'autumn', $thanks, 'Hidden field.' );
		$this->assertStringContainsString( '••••', $thanks );
		$this->assertStringNotContainsString( '1234', $thanks );
		$this->assertStringNotContainsString( 'from blocks', $thanks, 'WooCommerce shows block values itself.' );
		$this->assertStringContainsString( 'Visible later', $display->table_html( $order, 'my_account' ) );
	}

	public function test_customer_pages_only_show_the_order_to_its_owner(): void {
		$this->save( 'order', 'gift_note', 'text', 'Gift note' );
		$order = wc_create_order();
		$order->update_meta_data( '_rxcf_gift_note', 'Happy birthday' );
		$order->save();
		$display = new CustomerDisplay( $this->values );

		try {
			$this->assertSame( '', $this->capture( static fn() => $display->render_thank_you( $order->get_id() ) ), 'Guest without the order key.' );

			$_GET['key'] = $order->get_order_key();
			$this->assertStringContainsString( 'Happy birthday', $this->capture( static fn() => $display->render_thank_you( $order->get_id() ) ) );

			$_GET['key'] = 'wc_order_wrong';
			$this->assertSame( '', $this->capture( static fn() => $display->render_my_account( $order->get_id() ) ) );
		} finally {
			$order->delete( true );
		}
	}

	public function test_emails_follow_the_store_and_customer_switches(): void {
		$this->save( 'order', 'gift_note', 'text', 'Gift note' );
		$this->save( 'order', 'store_only', 'text', 'Store only', display: new DisplayFlags( customer_email: false ) );
		$this->save( 'order', 'source', 'hidden', 'Source' );
		$this->save( 'order', 'block_value', 'text', 'Block value' );

		$order = new \WC_Order();
		$order->update_meta_data( '_rxcf_gift_note', 'Happy birthday' );
		$order->update_meta_data( '_rxcf_store_only', 'internal' );
		$order->update_meta_data( '_rxcf_source', 'autumn' );
		$order->update_meta_data( '_wc_other/rachnix/block_value', 'from blocks' );
		$email = new EmailDisplay( $this->values );

		$to_store    = $email->add_fields( array(), true, $order );
		$to_customer = $email->add_fields( array(), false, $order );

		$this->assertSame( array( 'rxcf_gift_note', 'rxcf_source', 'rxcf_store_only' ), array_keys( $to_store ) );
		$this->assertSame( array( 'rxcf_gift_note' ), array_keys( $to_customer ) );
		$this->assertSame(
			array(
				'label' => 'Gift note',
				'value' => 'Happy birthday',
			),
			$to_customer['rxcf_gift_note']
		);
	}

	public function test_block_values_follow_the_same_switches(): void {
		$this->save( 'order', 'gift_note', 'text', 'Gift note', display: new DisplayFlags( customer_email: false, my_account: false ) );
		$filter = new BlockFieldVisibility( $this->repository );
		$ours   = array( 'id' => 'rachnix/gift_note' );

		$this->assertTrue( $filter->filter( true, $ours, array(), array( 'caller' => 'WC_Email::additional_checkout_fields', 'sent_to_admin' => true ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertFalse( $filter->filter( true, $ours, array(), array( 'caller' => 'WC_Email::additional_checkout_fields', 'sent_to_admin' => false ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertTrue( $filter->filter( true, array( 'id' => 'other-plugin/thing' ), array(), array( 'caller' => 'WC_Email::x' ) ), "Other plugins' fields are untouched." );
		$this->assertFalse( $filter->filter( false, array( 'id' => 'rachnix/unknown' ), array(), array() ), 'Unknown keys keep WooCommerce\'s answer.' );
	}

	public function test_rest_orders_include_values_from_both_checkouts(): void {
		$this->save( 'order', 'gift_note', 'text', 'Gift note' );
		$this->save( 'order', 'pin', 'password', 'PIN' );
		$this->save( 'billing', 'vat', 'text', 'VAT' );

		$order = new \WC_Order();
		$order->update_meta_data( '_rxcf_gift_note', 'Happy birthday' );
		$order->update_meta_data( '_rxcf_pin', '1234' );
		$order->update_meta_data( '_wc_billing/rachnix/vat', 'GB123' );

		$rest     = new OrderFieldsRest( $this->values );
		$response = $rest->add_values( new \WP_REST_Response( array( 'id' => 1 ) ), $order );
		$data     = array_column( $response->get_data()[ OrderFieldsRest::PROPERTY ], null, 'key' );

		$this->assertSame( 'Happy birthday', $data['gift_note']['value'] );
		$this->assertSame( 'classic', $data['gift_note']['source'] );
		$this->assertSame( '1234', $data['pin']['display'], 'Integrations get the real value.' );
		$this->assertSame( 'GB123', $data['vat']['value'] );
		$this->assertSame( 'blocks', $data['vat']['source'] );
		$this->assertSame( 1, $response->get_data()['id'], 'WooCommerce data is kept.' );
	}

	private function save( string $section, string $key, string $type, string $label, FieldSettings $settings = new FieldSettings(), ?DisplayFlags $display = null, bool $enabled = true ): void {
		if ( null !== $display ) {
			$settings = new FieldSettings( $settings->default_value, $settings->required, $settings->width, $settings->css_classes, $settings->options, $settings->validation, $display );
		}
		$this->repository->save( new Field( wp_generate_uuid4(), SectionKey::from_string( $section ), $key, $type, $label, settings: $settings, enabled: $enabled ) );
	}

	/**
	 * @param array<string, mixed> $values Values.
	 * @return array<string, mixed>
	 */
	private function sorted( array $values ): array {
		ksort( $values );
		return $values;
	}

	private function capture( callable $render ): string {
		ob_start();
		$render();
		return (string) ob_get_clean();
	}

	private function unregister( OrderAdminDisplay $admin ): void {
		remove_action( 'woocommerce_admin_order_data_after_shipping_address', array( $admin, 'render_order_box' ) );
		remove_filter( 'manage_woocommerce_page_wc-orders_columns', array( $admin, 'add_columns' ) );
		remove_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $admin, 'render_order_column' ) );
		remove_filter( 'manage_edit-shop_order_columns', array( $admin, 'add_columns' ) );
		remove_action( 'manage_shop_order_posts_custom_column', array( $admin, 'render_post_column' ) );
	}
}
