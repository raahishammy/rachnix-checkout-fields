<?php
/**
 * Number field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\ValidationError;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * A number, with an optional minimum and maximum.
 */
final class NumberField extends AbstractFieldType {

	public function slug(): string {
		return 'number';
	}

	public function label(): string {
		return __( 'Number', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A number, with an optional minimum and maximum.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'calculator';
	}

	public function allowed_rules(): array {
		return array( ValidationRuleType::NumberRange );
	}

	/**
	 * Plain decimal only: optional minus, digits, optional fraction with a dot.
	 * Rejects exponents (1e5), hex (0x1A), INF, NaN, commas and spaces.
	 *
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition.
	 */
	public function value_errors( string $value, Field $field ): array {
		return 1 === preg_match( '/^-?[0-9]{1,15}(?:\.[0-9]{1,10})?$/D', $value )
			? array()
			: array( new ValidationError( $field->key, ValidationErrorCode::InvalidNumber ) );
	}
}
