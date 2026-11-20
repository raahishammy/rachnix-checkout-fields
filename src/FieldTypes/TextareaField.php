<?php
/**
 * Text area field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * Several lines of text, such as delivery instructions.
 */
final class TextareaField extends AbstractFieldType {

	public function slug(): string {
		return 'textarea';
	}

	public function label(): string {
		return __( 'Text area', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'Several lines of text, such as delivery instructions.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'editor-paragraph';
	}

	public function allowed_rules(): array {
		return array( ValidationRuleType::Length, ValidationRuleType::Pattern );
	}

	public function max_length(): int {
		return 5000;
	}

	/**
	 * Like the single-line sanitiser, but keeps line breaks.
	 *
	 * @param mixed $raw Submitted value.
	 */
	public function sanitize( mixed $raw ): string {
		return sanitize_textarea_field( $this->scalar_to_string( $raw ) );
	}
}
