<?php
/**
 * Applies the "Show in" switches to WooCommerce's display of block checkout values.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Display;

use Rachnix\CheckoutFields\Checkout\Blocks\BlocksCheckoutIntegration;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce shows block checkout values in emails, on the thank-you page and in My Account,
 * and asks `woocommerce_filter_fields_for_order_confirmation` (WooCommerce 10.1+) per field.
 * For this plugin's fields the answer follows the field's switches. On older WooCommerce the
 * filter does not exist and only "Thank-you page" (show_in_order_confirmation) applies.
 * The admin order screen is not filterable: WooCommerce always shows them there.
 */
final class BlockFieldVisibility implements Hookable {

	/**
	 * @param FieldRepositoryInterface $fields Field definitions.
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_filter_fields_for_order_confirmation', array( $this, 'filter' ), 10, 4 );
	}

	/**
	 * @param mixed $show    WooCommerce's answer so far.
	 * @param mixed $field   Additional field definition.
	 * @param mixed $fields  All fields shown together.
	 * @param mixed $context Where they are shown (caller, sent_to_admin...).
	 */
	public function filter( mixed $show, mixed $field, mixed $fields = null, mixed $context = null ): bool {
		$ours = $this->field_for( $field );
		if ( null === $ours ) {
			return (bool) $show;
		}

		$display = $ours->settings->display;
		$caller  = is_array( $context ) && is_string( $context['caller'] ?? null ) ? $context['caller'] : '';

		if ( str_starts_with( $caller, 'WC_Email::' ) ) {
			return true === ( $context['sent_to_admin'] ?? false ) ? $display->admin_email : $display->customer_email;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return $display->thank_you;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return $display->my_account;
		}
		return (bool) $show;
	}

	/**
	 * @param mixed $field Additional field definition from WooCommerce.
	 */
	private function field_for( mixed $field ): ?Field {
		$prefix = BlocksCheckoutIntegration::ID_NAMESPACE . '/';
		$id     = is_array( $field ) && is_string( $field['id'] ?? null ) ? $field['id'] : '';
		if ( ! str_starts_with( $id, $prefix ) ) {
			return null;
		}
		$key = substr( $id, strlen( $prefix ) );
		foreach ( $this->fields->all() as $candidate ) {
			if ( ! $candidate->is_core && $candidate->key === $key ) {
				return $candidate;
			}
		}
		return null;
	}
}
