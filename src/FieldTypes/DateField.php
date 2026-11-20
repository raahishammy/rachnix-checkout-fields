<?php
/**
 * Date field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * A date picked from a calendar, such as a delivery date.
 */
final class DateField extends AbstractFieldType {

	public function slug(): string {
		return 'date';
	}

	public function label(): string {
		return __( 'Date', 'rachnix-checkout-fields' );
	}

	public function description(): string {
		return __( 'A date picked from a calendar, such as a delivery date.', 'rachnix-checkout-fields' );
	}

	public function icon(): string {
		return 'calendar-alt';
	}

	public function supports_placeholder(): bool {
		return false;
	}
}
