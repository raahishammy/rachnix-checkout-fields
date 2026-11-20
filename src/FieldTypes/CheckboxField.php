<?php
/**
 * Checkbox field type.
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
 * A single tick box, such as "Gift wrap this order".
 */
final class CheckboxField extends AbstractFieldType {

	public function slug(): string {
		return 'checkbox';
	}

	public function label(): string {
		return __( 'Checkbox', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A single tick box, such as "Gift wrap this order".', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'yes';
	}

	public function supports_placeholder(): bool {
		return false;
	}

	/**
	 * Ticked becomes "1", anything else "" (not ticked).
	 *
	 * @param mixed $raw Submitted value.
	 */
	public function sanitize( mixed $raw ): string {
		return in_array( $raw, array( true, 1, '1', 'on', 'yes', 'true' ), true ) ? '1' : '';
	}

	/**
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition.
	 */
	public function value_errors( string $value, Field $field ): array {
		return '1' === $value ? array() : array( new ValidationError( $field->key, ValidationErrorCode::InvalidChoice ) );
	}
}
