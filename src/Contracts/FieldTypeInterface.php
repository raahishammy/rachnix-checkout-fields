<?php
/**
 * Contract for a field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Contracts;

use Rachnix\CheckoutFields\Domain\ValidationRuleType;

defined( 'ABSPATH' ) || exit;

/**
 * Describes what a field type is and which settings it accepts.
 * New types (including Pro ones) are added by registering a class in FieldTypeRegistry.
 */
interface FieldTypeInterface {

	/**
	 * Stable slug stored in `rxcf_fields.type`. Must match Patterns::TYPE_SLUG.
	 */
	public function slug(): string;

	/**
	 * Translated name for the field-type picker.
	 */
	public function label(): string;

	/**
	 * Translated one-line description with an example, for the field-type picker.
	 */
	public function description(): string;

	/**
	 * Dashicons name (without the "dashicons-" prefix) for the field-type picker.
	 */
	public function icon(): string;

	/**
	 * Whether the store owner defines a list of choices.
	 */
	public function supports_options(): bool;

	/**
	 * Whether a placeholder can be set.
	 */
	public function supports_placeholder(): bool;

	/**
	 * Whether the field can be marked required.
	 */
	public function can_be_required(): bool;

	/**
	 * Validation rules the store owner may add to this type.
	 *
	 * @return list<ValidationRuleType>
	 */
	public function allowed_rules(): array;
}
