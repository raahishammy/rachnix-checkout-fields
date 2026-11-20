<?php
/**
 * One rejected value.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A validation failure for one field, with what the customer needs to fix it.
 */
final readonly class ValidationError {

	/**
	 * @param string              $field_key Field the error belongs to.
	 * @param ValidationErrorCode $code      What is wrong.
	 * @param float|null          $min       Lower limit (length or value), when relevant.
	 * @param float|null          $max       Upper limit (length or value), when relevant.
	 * @param string|null         $hint      Example of a valid value, for pattern errors.
	 */
	public function __construct(
		public string $field_key,
		public ValidationErrorCode $code,
		public ?float $min = null,
		public ?float $max = null,
		public ?string $hint = null,
	) {}

	/**
	 * Customer-facing sentence. Plain text: the caller escapes it for its output context.
	 *
	 * @param string $label Field label as the customer sees it.
	 */
	public function message( string $label ): string {
		$min = null === $this->min ? '' : self::number( $this->min );
		$max = null === $this->max ? '' : self::number( $this->max );

		return match ( $this->code ) {
			/* translators: %s: field label. */
			ValidationErrorCode::Required        => sprintf( __( '%s is a required field.', 'rachnix-checkout-fields' ), $label ),
			/* translators: 1: field label, 2: minimum number of characters. */
			ValidationErrorCode::TooShort        => sprintf( __( '%1$s must be at least %2$s characters long.', 'rachnix-checkout-fields' ), $label, $min ),
			/* translators: 1: field label, 2: maximum number of characters. */
			ValidationErrorCode::TooLong         => sprintf( __( '%1$s must be at most %2$s characters long.', 'rachnix-checkout-fields' ), $label, $max ),
			/* translators: %s: field label. */
			ValidationErrorCode::InvalidEmail    => sprintf( __( '%s is not a valid email address.', 'rachnix-checkout-fields' ), $label ),
			/* translators: %s: field label. */
			ValidationErrorCode::InvalidPhone    => sprintf( __( '%s is not a valid phone number.', 'rachnix-checkout-fields' ), $label ),
			/* translators: %s: field label. */
			ValidationErrorCode::InvalidNumber   => sprintf( __( '%s must be a number.', 'rachnix-checkout-fields' ), $label ),
			ValidationErrorCode::OutOfRange      => $this->range_message( $label, $min, $max ),
			/* translators: %s: field label. */
			ValidationErrorCode::InvalidDate     => sprintf( __( '%s must be a valid date.', 'rachnix-checkout-fields' ), $label ),
			/* translators: %s: field label. */
			ValidationErrorCode::InvalidChoice   => sprintf( __( 'Please choose a valid option for %s.', 'rachnix-checkout-fields' ), $label ),
			ValidationErrorCode::PatternMismatch => null === $this->hint
				/* translators: %s: field label. */
				? sprintf( __( '%s is not in the expected format.', 'rachnix-checkout-fields' ), $label )
				/* translators: 1: field label, 2: example of a valid value. */
				: sprintf( __( '%1$s is not in the expected format, for example %2$s.', 'rachnix-checkout-fields' ), $label, $this->hint ),
		};
	}

	/**
	 * @param string $label Field label.
	 * @param string $min   Formatted minimum, or ''.
	 * @param string $max   Formatted maximum, or ''.
	 */
	private function range_message( string $label, string $min, string $max ): string {
		if ( '' !== $min && '' !== $max ) {
			/* translators: 1: field label, 2: minimum, 3: maximum. */
			return sprintf( __( '%1$s must be between %2$s and %3$s.', 'rachnix-checkout-fields' ), $label, $min, $max );
		}
		if ( '' !== $min ) {
			/* translators: 1: field label, 2: minimum. */
			return sprintf( __( '%1$s must be %2$s or more.', 'rachnix-checkout-fields' ), $label, $min );
		}
		/* translators: 1: field label, 2: maximum. */
		return sprintf( __( '%1$s must be %2$s or less.', 'rachnix-checkout-fields' ), $label, $max );
	}

	/**
	 * "5" rather than "5.0", "2.5" stays "2.5".
	 *
	 * @param float $value Number.
	 */
	private static function number( float $value ): string {
		return rtrim( rtrim( sprintf( '%.6F', $value ), '0' ), '.' );
	}
}
