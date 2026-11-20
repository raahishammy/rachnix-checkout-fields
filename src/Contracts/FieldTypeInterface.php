<?php
/**
 * Contract for a field type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Contracts;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\ValidationError;
use Rachnix\CheckoutFields\Domain\ValidationRule;
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

	/**
	 * Rules every value of this type must pass, whatever the store owner configured.
	 *
	 * @return list<ValidationRule>
	 */
	public function built_in_rules(): array;

	/**
	 * Longest accepted value, in characters. Checked before any other rule, so
	 * no regex ever runs on an oversized value.
	 */
	public function max_length(): int;

	/**
	 * Turns raw submitted input into the clean string that is validated and stored.
	 * Input must already be unslashed. Never throws; non-scalar input becomes ''.
	 *
	 * @param mixed $raw Submitted value.
	 */
	public function sanitize( mixed $raw ): string;

	/**
	 * Format errors of a sanitised, non-empty value (e.g. "not a number", "not one of the options").
	 *
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition (for options and the error key).
	 * @return list<ValidationError>
	 */
	public function value_errors( string $value, Field $field ): array;
}
