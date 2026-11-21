<?php
/**
 * Field values in WooCommerce emails.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Display;

use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Adds classic-checkout values to WooCommerce's order meta block, for HTML and plain-text
 * emails alike. Each field has its own switch for emails to the store and to the customer.
 * (WooCommerce adds block-checkout values itself; BlockFieldVisibility applies the switches.)
 */
final class EmailDisplay implements Hookable {

	/**
	 * @param OrderFieldValues $values Field values of orders.
	 */
	public function __construct(
		private readonly OrderFieldValues $values,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'add_fields' ), 10, 3 );
	}

	/**
	 * WooCommerce passes label and value through wp_kses_post() for HTML and plain text, so
	 * plain strings go in (sanitised on input; no markup).
	 *
	 * @param mixed $fields        Meta fields: key => [label, value].
	 * @param mixed $sent_to_admin Whether the email goes to the store.
	 * @param mixed $order         Order.
	 * @return mixed
	 */
	public function add_fields( mixed $fields, mixed $sent_to_admin = false, mixed $order = null ): mixed {
		if ( ! is_array( $fields ) || ! $order instanceof \WC_Order ) {
			return $fields;
		}
		$to_store = true === $sent_to_admin;

		foreach ( $this->values->for_order( $order ) as $value ) {
			$display = $value->field->settings->display;
			if ( ! $value->is_classic() || ! ( $to_store ? $display->admin_email : $display->customer_email ) ) {
				continue;
			}
			$text = $this->values->display( $value, $to_store );
			if ( null === $text || '' === $text ) {
				continue;
			}
			$fields[ 'rxcf_' . $value->field->key ] = array(
				'label' => $this->values->label( $value ),
				'value' => $text,
			);
		}
		return $fields;
	}
}
