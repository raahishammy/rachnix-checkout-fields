<?php
/**
 * Checkbox field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

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
}
