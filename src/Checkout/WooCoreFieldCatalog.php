<?php
/**
 * WooCommerce's default checkout fields.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout;

use Rachnix\CheckoutFields\Contracts\CoreFieldCatalogInterface;
use Rachnix\CheckoutFields\Domain\CoreFieldDefault;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\Width;

defined( 'ABSPATH' ) || exit;

/**
 * Reads billing and shipping fields from WC_Countries for the store's base country
 * (so other plugins' changes to address fields are included), plus order notes.
 * Our own woocommerce_checkout_fields filter is not involved, so these are true defaults.
 */
final class WooCoreFieldCatalog implements CoreFieldCatalogInterface {

	/**
	 * @var list<CoreFieldDefault>|null
	 */
	private ?array $defaults = null;

	public function all(): array {
		if ( null !== $this->defaults ) {
			return $this->defaults;
		}

		$defaults = array();
		if ( function_exists( 'WC' ) && WC()->countries instanceof \WC_Countries ) {
			$country = WC()->countries->get_base_country();
			foreach ( array( array( Section::Billing, 'billing_' ), array( Section::Shipping, 'shipping_' ) ) as [ $section, $prefix ] ) {
				foreach ( WC()->countries->get_address_fields( $country, $prefix ) as $key => $args ) {
					if ( is_string( $key ) && is_array( $args ) ) {
						$defaults[] = $this->from_args( SectionKey::core( $section ), $key, $args );
					}
				}
			}
		}
		$defaults[] = new CoreFieldDefault( SectionKey::core( Section::Order ), 'order_comments', __( 'Order notes', 'rachnix-checkout-fields' ), false, 10, Width::Full );

		$this->defaults = $defaults;
		return $defaults;
	}

	public function find( SectionKey $section, string $key ): ?CoreFieldDefault {
		foreach ( $this->all() as $default ) {
			if ( $default->key === $key && $default->section->equals( $section ) ) {
				return $default;
			}
		}
		return null;
	}

	/**
	 * @param SectionKey   $section Section.
	 * @param string       $key     Field key.
	 * @param array<mixed> $args    WooCommerce field arguments.
	 */
	private function from_args( SectionKey $section, string $key, array $args ): CoreFieldDefault {
		$classes  = is_array( $args['class'] ?? null ) ? $args['class'] : array();
		$half     = array() !== array_intersect( array( 'form-row-first', 'form-row-last' ), $classes );
		$required = $args['required'] ?? false;

		return new CoreFieldDefault(
			$section,
			$key,
			// A theme or plugin may blank a label; the key keeps the field nameable in the editor.
			is_string( $args['label'] ?? null ) && '' !== trim( $args['label'] ) ? $args['label'] : $key,
			true === $required,
			is_numeric( $args['priority'] ?? null ) ? (int) $args['priority'] : 10,
			$half ? Width::Half : Width::Full,
		);
	}
}
