<?php
/**
 * Password field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * Text hidden while typing, such as a gift card PIN.
 */
final class PasswordField extends AbstractFieldType {

	public function slug(): string {
		return 'password';
	}

	public function label(): string {
		return __( 'Password', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'Text hidden while typing, such as a gift card PIN.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'lock';
	}

	public function allowed_rules(): array {
		return array( ValidationRuleType::Length, ValidationRuleType::Pattern );
	}
}
