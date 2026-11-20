<?php
/**
 * One choice of a select or radio field.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Value saved with the order, and the label the customer sees.
 */
final readonly class FieldOption {

	/**
	 * @param string $value Saved value.
	 * @param string $label Shown label.
	 */
	public function __construct(
		public string $value,
		public string $label,
	) {}

	/**
	 * Reads stored data. Returns null when the entry has no usable value.
	 *
	 * @param mixed $data Decoded JSON.
	 */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) || ! is_scalar( $data['value'] ?? null ) ) {
			return null;
		}
		$value = (string) $data['value'];
		if ( '' === $value ) {
			return null;
		}
		$label = is_scalar( $data['label'] ?? null ) ? (string) $data['label'] : $value;

		return new self( $value, $label );
	}

	/**
	 * @return array{value: string, label: string}
	 */
	public function to_array(): array {
		return array(
			'value' => $this->value,
			'label' => $this->label,
		);
	}
}
