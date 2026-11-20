<?php
/**
 * Number field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

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
}
