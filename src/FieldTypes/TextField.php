<?php
/**
 * Text field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * A single line of text, such as a company VAT number.
 */
final class TextField extends AbstractFieldType {

	public function slug(): string {
		return 'text';
	}

	public function label(): string {
		return __( 'Text', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A single line of text, such as a company VAT number.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'editor-textcolor';
	}

	public function allowed_rules(): array {
		return array( ValidationRuleType::Length, ValidationRuleType::Pattern );
	}
}
