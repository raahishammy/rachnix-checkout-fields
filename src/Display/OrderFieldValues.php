<?php
/**
 * Reads and formats custom field values of an order, from either checkout.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Display;

use Rachnix\CheckoutFields\Checkout\Blocks\BlocksCheckoutIntegration;
use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\I18n\StringTranslations;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that finds a field's value on an order:
 * - classic checkout: order meta `_rxcf_{key}` (OrderDataWriter);
 * - block checkout: WooCommerce's storage, `_wc_{billing|shipping|other}/rachnix/{key}`.
 * Meta is read directly, so values stay readable even when the field is no longer registered
 * with the block checkout (e.g. the store went back to the classic checkout).
 *
 * Fields that were deleted are not listed; their values stay on the order.
 */
final class OrderFieldValues {

	/** WooCommerce's Additional Checkout Fields meta prefixes, stable since WooCommerce 8.9. */
	private const BLOCK_PREFIXES = array(
		'billing'  => '_wc_billing/',
		'shipping' => '_wc_shipping/',
		'other'    => '_wc_other/',
	);

	private const MASK = '••••';

	/**
	 * @param FieldRepositoryInterface $fields Field definitions.
	 * @param FieldTypeRegistry        $types  Field types.
	 * @param OrderDataWriter          $writer Classic storage.
	 * @param StringTranslations|null  $i18n   Translations of store-owner texts (WPML, Polylang).
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
		private readonly FieldTypeRegistry $types,
		private readonly OrderDataWriter $writer,
		private readonly ?StringTranslations $i18n = null,
	) {}

	/**
	 * Every custom field with a value on the order, in checkout order.
	 *
	 * @param \WC_Order $order Order.
	 * @return list<OrderFieldValue>
	 */
	public function for_order( \WC_Order $order ): array {
		$values = array();
		foreach ( $this->fields->all() as $field ) {
			if ( $field->is_core ) {
				continue;
			}
			$field = $this->i18n?->field( $field ) ?? $field; // Labels and choices in the reader's language.

			$classic = $this->writer->read( $order, $field );
			if ( '' !== $classic ) {
				$values[] = new OrderFieldValue( $field, $classic, OrderFieldValue::SOURCE_CLASSIC );
				continue;
			}

			$group = BlocksCheckoutIntegration::storage_group( $field );
			$raw   = $order->get_meta( self::BLOCK_PREFIXES[ $group ] . BlocksCheckoutIntegration::field_id( $field ), true );
			$block = true === $raw ? '1' : ( is_scalar( $raw ) ? (string) $raw : '' );
			if ( '' !== $block ) {
				$values[] = new OrderFieldValue( $field, $block, OrderFieldValue::SOURCE_BLOCKS );
			}
		}
		return $values;
	}

	/**
	 * The value as people should read it, or null when this audience must not see it.
	 * Hidden fields are for the store only; password-type values are masked for customers
	 * and in emails. Plain text: escape it for the output.
	 *
	 * @param OrderFieldValue $value    Value.
	 * @param bool            $for_store True for the store's own screens and emails to the store.
	 * @param bool            $unmasked  True only where the store needs the real secret (order screen, API).
	 */
	public function display( OrderFieldValue $value, bool $for_store, bool $unmasked = false ): ?string {
		$slug = $value->field->type;
		if ( 'hidden' === $slug && ! $for_store ) {
			return null;
		}
		if ( 'password' === $slug && ! $unmasked ) {
			return self::MASK;
		}
		return $this->types->has( $slug ) ? $this->types->get( $slug )->format_value( $value->value, $value->field ) : $value->value;
	}

	/**
	 * The label people see for a field.
	 *
	 * @param OrderFieldValue $value Value.
	 */
	public function label( OrderFieldValue $value ): string {
		return '' !== $value->field->label ? $value->field->label : $value->field->key;
	}
}
