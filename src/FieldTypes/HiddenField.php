<?php
/**
 * Hidden field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * A value saved with the order but not shown to the customer, such as a campaign code.
 */
final class HiddenField extends AbstractFieldType {

	public function slug(): string {
		return 'hidden';
	}

	public function label(): string {
		return __( 'Hidden', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A value saved with the order but not shown to the customer, such as a campaign code.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'hidden';
	}

	public function supports_placeholder(): bool {
		return false;
	}

	public function can_be_required(): bool {
		return false;
	}
}
