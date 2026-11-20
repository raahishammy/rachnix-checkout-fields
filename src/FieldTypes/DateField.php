<?php
/**
 * Date field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\ValidationError;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode;

defined( 'ABSPATH' ) || exit;

/**
 * A date picked from a calendar, such as a delivery date.
 */
final class DateField extends AbstractFieldType {

	public function slug(): string {
		return 'date';
	}

	public function label(): string {
		return __( 'Date', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A date picked from a calendar, such as a delivery date.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'calendar-alt';
	}

	public function supports_placeholder(): bool {
		return false;
	}

	public function max_length(): int {
		return 10;
	}

	/**
	 * Must be a real calendar date in the HTML date input format, YYYY-MM-DD.
	 *
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition.
	 */
	public function value_errors( string $value, Field $field ): array {
		if ( 1 === preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts )
			&& checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return array();
		}
		return array( new ValidationError( $field->key, ValidationErrorCode::InvalidDate ) );
	}
}
