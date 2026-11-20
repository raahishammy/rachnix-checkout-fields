<?php
/**
 * Radio buttons field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A short list of choices shown all at once, such as a gift message style.
 */
final class RadioField extends AbstractFieldType {

	public function slug(): string {
		return 'radio';
	}

	public function label(): string {
		return __( 'Radio buttons', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A short list of choices shown all at once, such as a gift message style.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'marker';
	}

	public function supports_options(): bool {
		return true;
	}

	public function supports_placeholder(): bool {
		return false;
	}

	/**
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition.
	 */
	public function value_errors( string $value, Field $field ): array {
		return $this->choice_errors( $value, $field );
	}
}
