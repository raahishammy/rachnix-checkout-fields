<?php
/**
 * Which field types the block checkout can show.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Maps our field types to WooCommerce's Additional Checkout Fields types.
 *
 * - text, select, checkbox: native in every supported WooCommerce version.
 * - email, phone: shown as text fields with the matching autocomplete hint; their format
 *   is checked server-side by ValidationService like everywhere else.
 * - date: native from WooCommerce 11.2 (detected by its DateFieldType class).
 * - textarea, number, password, radio, hidden: not available; listed in the admin notice.
 *   An add-on that renders a type itself declares it with `rxcf_block_checkout_renders_type`.
 */
final class BlockFieldSupport {

	private const NATIVE = array(
		'text'     => 'text',
		'email'    => 'text',
		'phone'    => 'text',
		'select'   => 'select',
		'checkbox' => 'checkbox',
	);

	private const AUTOCOMPLETE = array(
		'email' => 'email',
		'phone' => 'tel',
	);

	/** WooCommerce class that adds the native date type. */
	public const DATE_TYPE_CLASS = 'Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldTypes\DateFieldType';

	/**
	 * @param bool|null $date_supported Override for tests; null detects from WooCommerce.
	 */
	public function __construct(
		private readonly ?bool $date_supported = null,
	) {}

	/**
	 * WooCommerce Additional Checkout Fields type for one of our types, or null when there is none.
	 *
	 * @param string $slug Our field type.
	 */
	public function native_type( string $slug ): ?string {
		if ( 'date' === $slug ) {
			return $this->date_supported() ? 'date' : null;
		}
		return self::NATIVE[ $slug ] ?? null;
	}

	/**
	 * Browser autofill hint for text-mapped types.
	 *
	 * @param string $slug Our field type.
	 */
	public function autocomplete( string $slug ): ?string {
		return self::AUTOCOMPLETE[ $slug ] ?? null;
	}

	/**
	 * Whether fields of this type appear on the block checkout, natively or through an add-on.
	 *
	 * @param string $slug Our field type.
	 */
	public function is_shown( string $slug ): bool {
		if ( null !== $this->native_type( $slug ) ) {
			return true;
		}

		/**
		 * Whether an add-on renders this field type on the block checkout itself.
		 *
		 * @param bool   $renders Default false.
		 * @param string $slug    Field type slug.
		 */
		return true === apply_filters( 'rxcf_block_checkout_renders_type', false, $slug );
	}

	public function date_supported(): bool {
		return $this->date_supported ?? class_exists( self::DATE_TYPE_CLASS );
	}
}
