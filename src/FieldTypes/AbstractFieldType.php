<?php
/**
 * Defaults shared by field types.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A plain input: no choices, placeholder allowed, can be required, no extra rules.
 * Types override only what differs.
 */
abstract class AbstractFieldType implements FieldTypeInterface {

	public function supports_options(): bool {
		return false;
	}

	public function supports_placeholder(): bool {
		return true;
	}

	public function can_be_required(): bool {
		return true;
	}

	public function allowed_rules(): array {
		return array();
	}
}
