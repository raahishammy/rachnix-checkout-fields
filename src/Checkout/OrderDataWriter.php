<?php
/**
 * Saves checkout values on the order.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout;

use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that writes field values to orders. Uses WC_Order meta methods only,
 * so it works with HPOS and legacy storage, and the values stay with the order if the
 * plugin is removed.
 */
final class OrderDataWriter {

	public const META_PREFIX = '_rxcf_';

	/**
	 * Order meta key. Field keys are unique across sections, so this is unambiguous.
	 *
	 * @param Field $field Field.
	 */
	public function meta_key( Field $field ): string {
		return self::META_PREFIX . $field->key;
	}

	/**
	 * Sets the value on the order object; WooCommerce saves the order afterwards.
	 *
	 * @param \WC_Order $order Order being created.
	 * @param Field     $field Field.
	 * @param string    $value Sanitised and validated value.
	 */
	public function write( \WC_Order $order, Field $field, string $value ): void {
		$order->update_meta_data( $this->meta_key( $field ), $value );
	}

	/**
	 * @param \WC_Order $order Order.
	 * @param Field     $field Field.
	 */
	public function read( \WC_Order $order, Field $field ): string {
		$value = $order->get_meta( $this->meta_key( $field ), true );
		return is_scalar( $value ) ? (string) $value : '';
	}
}
