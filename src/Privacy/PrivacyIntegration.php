<?php
/**
 * Field values in WordPress's personal data export and erase tools.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Privacy;

use Rachnix\CheckoutFields\Checkout\Blocks\BlocksCheckoutIntegration;
use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Display\OrderFieldValues;
use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WooCommerce's exporters and erasers (Tools > Export / Erase Personal Data):
 * - orders: values from either checkout are exported, and removed when WooCommerce removes
 *   the order's personal data (its "Retain personal data" settings decide when);
 * - customers: block checkout contact and address values that WooCommerce saved on the
 *   customer profile.
 * Exports carry the real values: it is the customer's own data.
 */
final class PrivacyIntegration implements Hookable {

	/** WooCommerce's Additional Checkout Fields meta prefixes (see OrderFieldValues). */
	private const BLOCK_PREFIXES = array( '_wc_billing/', '_wc_shipping/', '_wc_other/' );

	/**
	 * @param OrderFieldValues         $values Field values of orders.
	 * @param FieldRepositoryInterface $fields Field definitions.
	 * @param OrderDataWriter          $writer Classic storage.
	 */
	public function __construct(
		private readonly OrderFieldValues $values,
		private readonly FieldRepositoryInterface $fields,
		private readonly OrderDataWriter $writer,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( $this, 'export_order' ), 10, 2 );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'erase_order' ) );
		add_filter( 'woocommerce_privacy_export_customer_personal_data', array( $this, 'export_customer' ), 10, 2 );
		add_filter( 'woocommerce_privacy_erase_personal_data_customer', array( $this, 'erase_customer' ), 10, 2 );
	}

	/**
	 * @param mixed $data  Export rows: list of [name, value].
	 * @param mixed $order Order.
	 * @return mixed
	 */
	public function export_order( mixed $data, mixed $order ): mixed {
		if ( ! is_array( $data ) || ! $order instanceof \WC_Order ) {
			return $data;
		}
		foreach ( $this->values->for_order( $order ) as $value ) {
			$data[] = array(
				'name'  => $this->values->label( $value ),
				'value' => (string) $this->values->display( $value, true, true ),
			);
		}
		return $data;
	}

	/**
	 * Runs after WooCommerce saved the anonymised order, so this saves again.
	 *
	 * @param mixed $order Order.
	 */
	public function erase_order( mixed $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$changed = false;
		foreach ( $this->custom_fields() as $field ) {
			foreach ( $this->meta_keys( $field ) as $meta_key ) {
				if ( $order->meta_exists( $meta_key ) ) {
					$order->delete_meta_data( $meta_key );
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			$order->save();
		}
	}

	/**
	 * @param mixed $data     Export rows.
	 * @param mixed $customer Customer.
	 * @return mixed
	 */
	public function export_customer( mixed $data, mixed $customer ): mixed {
		if ( ! is_array( $data ) || ! $customer instanceof \WC_Customer ) {
			return $data;
		}
		foreach ( $this->custom_fields() as $field ) {
			foreach ( $this->block_keys( $field ) as $meta_key ) {
				$value = $customer->get_meta( $meta_key, true );
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$data[] = array(
						'name'  => '' !== $field->label ? $field->label : $field->key,
						'value' => (string) $value,
					);
				}
			}
		}
		return $data;
	}

	/**
	 * Runs after WooCommerce saved the customer, so this saves again.
	 *
	 * @param mixed $response Eraser response: messages, items_removed, ...
	 * @param mixed $customer Customer.
	 * @return mixed
	 */
	public function erase_customer( mixed $response, mixed $customer ): mixed {
		if ( ! is_array( $response ) || ! $customer instanceof \WC_Customer ) {
			return $response;
		}
		$removed = false;
		foreach ( $this->custom_fields() as $field ) {
			foreach ( $this->block_keys( $field ) as $meta_key ) {
				if ( $customer->meta_exists( $meta_key ) ) {
					$customer->delete_meta_data( $meta_key );
					$removed = true;
				}
			}
		}
		if ( $removed ) {
			$customer->save();
			$messages                  = is_array( $response['messages'] ?? null ) ? $response['messages'] : array();
			$messages[]                = __( 'Removed checkout field values saved on the customer.', 'rachnix-checkout-fields' );
			$response['messages']      = $messages;
			$response['items_removed'] = true;
		}
		return $response;
	}

	/**
	 * @return list<Field>
	 */
	private function custom_fields(): array {
		return array_values( array_filter( $this->fields->all()->to_list(), static fn( Field $field ): bool => ! $field->is_core ) );
	}

	/**
	 * Every key a field's value can be stored under on an order.
	 *
	 * @param Field $field Field.
	 * @return list<string>
	 */
	private function meta_keys( Field $field ): array {
		return array_merge( array( $this->writer->meta_key( $field ) ), $this->block_keys( $field ) );
	}

	/**
	 * @param Field $field Field.
	 * @return list<string>
	 */
	private function block_keys( Field $field ): array {
		return array_map( static fn( string $prefix ): string => $prefix . BlocksCheckoutIntegration::field_id( $field ), self::BLOCK_PREFIXES );
	}
}
