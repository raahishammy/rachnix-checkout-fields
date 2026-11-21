<?php
/**
 * Field values for the customer: thank-you page and My Account order view.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Display;

use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Uses `woocommerce_thankyou` and `woocommerce_view_order`, which fire with classic and block
 * themes alike (the order confirmation block fires `woocommerce_thankyou` too). Only classic
 * checkout values are added here; WooCommerce shows block checkout values itself, and
 * BlockFieldVisibility applies the same switches to them.
 */
final class CustomerDisplay implements Hookable {

	/**
	 * @param OrderFieldValues $values Field values of orders.
	 */
	public function __construct(
		private readonly OrderFieldValues $values,
	) {}

	public function register(): void {
		add_action( 'woocommerce_thankyou', array( $this, 'render_thank_you' ), 20 );
		add_action( 'woocommerce_view_order', array( $this, 'render_my_account' ), 20 );
	}

	/**
	 * @param mixed $order_id Order id.
	 */
	public function render_thank_you( mixed $order_id ): void {
		$order = $this->order( $order_id );
		if ( null !== $order ) {
			echo $this->table_html( $order, 'thank_you' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- table_html() escapes every value.
		}
	}

	/**
	 * @param mixed $order_id Order id.
	 */
	public function render_my_account( mixed $order_id ): void {
		$order = $this->order( $order_id );
		if ( null !== $order ) {
			echo $this->table_html( $order, 'my_account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- table_html() escapes every value.
		}
	}

	/**
	 * The values table in WooCommerce's own table markup, so the theme styles it.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $place "thank_you" or "my_account".
	 */
	public function table_html( \WC_Order $order, string $place ): string {
		$rows = '';
		foreach ( $this->values->for_order( $order ) as $value ) {
			$display = $value->field->settings->display;
			$shown   = 'thank_you' === $place ? $display->thank_you : $display->my_account;
			if ( ! $value->is_classic() || ! $shown ) {
				continue;
			}
			$text = $this->values->display( $value, false );
			if ( null === $text ) {
				continue;
			}
			$rows .= sprintf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( $this->values->label( $value ) ),
				nl2br( esc_html( $text ) )
			);
		}
		if ( '' === $rows ) {
			return '';
		}
		return '<section class="woocommerce-rxcf-fields"><h2 class="woocommerce-column__title">' . esc_html__( 'Additional information', 'rachnix-checkout-fields' ) . '</h2>'
			. '<table class="woocommerce-table shop_table rxcf-fields-table"><tbody>' . $rows . '</tbody></table></section>';
	}

	/**
	 * The order, only for someone allowed to see it here. WooCommerce has checked this before
	 * these hooks run; checking again costs little and guards against other callers.
	 *
	 * @param mixed $order_id Order id.
	 */
	private function order( mixed $order_id ): ?\WC_Order {
		$order = is_numeric( $order_id ) ? wc_get_order( (int) $order_id ) : false;
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		$customer = $order->get_customer_id();
		$allowed  = 0 === $customer
			? $this->has_order_key( $order )
			: ( get_current_user_id() === $customer || current_user_can( 'edit_shop_orders' ) );
		return $allowed ? $order : null;
	}

	/**
	 * Guest orders: the thank-you URL carries the order key.
	 *
	 * @param \WC_Order $order Guest order.
	 */
	private function has_order_key( \WC_Order $order ): bool {
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of WooCommerce's own order key.
		return '' !== $key && hash_equals( $order->get_order_key(), $key );
	}
}
