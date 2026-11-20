<?php
/**
 * Phone field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * A phone number, such as a second contact number.
 */
final class PhoneField extends AbstractFieldType {

	public function slug(): string {
		return 'phone';
	}

	public function label(): string {
		return __( 'Phone', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A phone number, such as a second contact number.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'phone';
	}

	public function allowed_rules(): array {
		return array( ValidationRuleType::Length, ValidationRuleType::Pattern );
	}

	public function built_in_rules(): array {
		return array( new ValidationRule( ValidationRuleType::Phone ) );
	}
}
