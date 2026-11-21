<?php
/**
 * Drop-down field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A drop-down list of choices, such as a preferred delivery slot.
 */
final class SelectField extends AbstractFieldType {

	public function slug(): string {
		return 'select';
	}

	public function label(): string {
		return __( 'Drop-down', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A drop-down list of choices, such as a preferred delivery slot.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'menu-alt';
	}

	public function supports_options(): bool {
		return true;
	}

	/**
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition.
	 */
	public function value_errors( string $value, Field $field ): array {
		return $this->choice_errors( $value, $field );
	}

	/**
	 * @param string $value Stored value.
	 * @param Field  $field Field definition.
	 */
	public function format_value( string $value, Field $field ): string {
		return $this->choice_label( $value, $field );
	}
}
