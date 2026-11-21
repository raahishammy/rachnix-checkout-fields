<?php
/**
 * Field values on the admin order screen and in the orders list.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Display;

use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Works with HPOS and legacy order storage: both screens fire the same order-data hook, and
 * the list columns are added for both list tables.
 *
 * On the order screen only classic-checkout values are added; WooCommerce already shows
 * block-checkout values there. The list columns show values from both checkouts.
 */
final class OrderAdminDisplay implements Hookable {

	public const MAX_LIST_COLUMNS = 2;

	private const COLUMN_PREFIX = 'rxcf_';

	private const CELL_LENGTH = 60;

	/**
	 * @param OrderFieldValues         $values Field values of orders.
	 * @param FieldRepositoryInterface $fields Field definitions.
	 */
	public function __construct(
		private readonly OrderFieldValues $values,
		private readonly FieldRepositoryInterface $fields,
	) {}

	public function register(): void {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render_order_box' ) );

		// HPOS orders list.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_column' ), 10, 2 );
		// Legacy (posts) orders list.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_post_column' ), 10, 2 );
	}

	/**
	 * @param mixed $order Order being edited.
	 */
	public function render_order_box( mixed $order ): void {
		if ( $order instanceof \WC_Order ) {
			echo $this->box_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- box_html() escapes every value.
		}
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public function box_html( \WC_Order $order ): string {
		$rows = '';
		foreach ( $this->values->for_order( $order ) as $value ) {
			if ( ! $value->is_classic() || ! $value->field->settings->display->order_admin ) {
				continue;
			}
			$text  = (string) $this->values->display( $value, true, true );
			$rows .= sprintf(
				'<p><strong>%s:</strong> %s</p>',
				esc_html( $this->values->label( $value ) ),
				nl2br( esc_html( $text ) )
			);
		}
		if ( '' === $rows ) {
			return '';
		}
		return '<div class="rxcf-order-fields" style="clear:both;padding-top:1em"><h3>' . esc_html__( 'Checkout fields', 'rachnix-checkout-fields' ) . '</h3>' . $rows . '</div>';
	}

	/**
	 * Fields chosen for the orders list (at most two).
	 *
	 * @return list<Field>
	 */
	public function list_fields(): array {
		$fields = array();
		foreach ( $this->fields->all() as $field ) {
			if ( ! $field->is_core && $field->settings->display->order_list ) {
				$fields[] = $field;
			}
		}
		return array_slice( $fields, 0, self::MAX_LIST_COLUMNS );
	}

	/**
	 * Adds the columns before WooCommerce's actions column (or at the end).
	 *
	 * @param mixed $columns Column key => title.
	 * @return mixed
	 */
	public function add_columns( mixed $columns ): mixed {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$ours = array();
		foreach ( $this->list_fields() as $field ) {
			$ours[ self::COLUMN_PREFIX . $field->key ] = esc_html( '' !== $field->label ? $field->label : $field->key );
		}
		if ( array() === $ours ) {
			return $columns;
		}

		$position = array_search( 'wc_actions', array_keys( $columns ), true );
		if ( false === $position ) {
			return $columns + $ours;
		}
		return array_slice( $columns, 0, $position, true ) + $ours + array_slice( $columns, $position, null, true );
	}

	/**
	 * HPOS list cell.
	 *
	 * @param mixed $column Column key.
	 * @param mixed $order  Order of the row.
	 */
	public function render_order_column( mixed $column, mixed $order ): void {
		if ( is_string( $column ) && $order instanceof \WC_Order ) {
			echo $this->cell_html( $column, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cell_html() escapes.
		}
	}

	/**
	 * Legacy list cell.
	 *
	 * @param mixed $column  Column key.
	 * @param mixed $post_id Order id of the row.
	 */
	public function render_post_column( mixed $column, mixed $post_id ): void {
		$order = is_numeric( $post_id ) ? wc_get_order( (int) $post_id ) : false;
		if ( is_string( $column ) && $order instanceof \WC_Order ) {
			echo $this->cell_html( $column, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cell_html() escapes.
		}
	}

	/**
	 * @param string    $column Column key.
	 * @param \WC_Order $order  Order.
	 */
	public function cell_html( string $column, \WC_Order $order ): string {
		if ( ! str_starts_with( $column, self::COLUMN_PREFIX ) ) {
			return '';
		}
		$key = substr( $column, strlen( self::COLUMN_PREFIX ) );
		foreach ( $this->values->for_order( $order ) as $value ) {
			if ( $value->field->key === $key ) {
				$text = (string) $this->values->display( $value, true );
				if ( mb_strlen( $text ) > self::CELL_LENGTH ) {
					$text = mb_substr( $text, 0, self::CELL_LENGTH - 1 ) . '…';
				}
				return esc_html( $text );
			}
		}
		return '';
	}
}
