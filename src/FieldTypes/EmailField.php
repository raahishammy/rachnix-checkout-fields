<?php
/**
 * Email field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * An email address, checked for a valid format.
 */
final class EmailField extends AbstractFieldType {

	public function slug(): string {
		return 'email';
	}

	public function label(): string {
		return __( 'Email', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'An email address, checked for a valid format.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'email';
	}

	public function allowed_rules(): array {
		return array( ValidationRuleType::Length );
	}
}
