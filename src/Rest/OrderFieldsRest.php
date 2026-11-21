<?php
/**
 * Field values on WooCommerce REST API orders.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Display\OrderFieldValues;

defined( 'ABSPATH' ) || exit;

/**
 * Adds `rxcf_fields` to orders in /wc/v3/orders (and other WooCommerce REST order responses),
 * for integrations: raw and readable values from either checkout. WooCommerce has already
 * checked that the caller may read the order. (Our meta keys start with "_", so WooCommerce's
 * own meta_data never includes them.)
 */
final class OrderFieldsRest implements Hookable {

	public const PROPERTY = 'rxcf_fields';

	/**
	 * @param OrderFieldValues $values Field values of orders.
	 */
	public function __construct(
		private readonly OrderFieldValues $values,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_values' ), 10, 2 );
	}

	/**
	 * @param mixed $response REST response.
	 * @param mixed $order    Order.
	 * @return mixed
	 */
	public function add_values( mixed $response, mixed $order ): mixed {
		if ( ! $response instanceof \WP_REST_Response || ! $order instanceof \WC_Order ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		$data[ self::PROPERTY ] = $this->data( $order );
		$response->set_data( $data );
		return $response;
	}

	/**
	 * @param \WC_Order $order Order.
	 * @return list<array<string, string>>
	 */
	public function data( \WC_Order $order ): array {
		$items = array();
		foreach ( $this->values->for_order( $order ) as $value ) {
			$items[] = array(
				'key'     => $value->field->key,
				'label'   => $this->values->label( $value ),
				'type'    => $value->field->type,
				'section' => $value->field->section->value,
				'value'   => $value->value,
				'display' => (string) $this->values->display( $value, true, true ),
				'source'  => $value->source,
			);
		}
		return $items;
	}
}
