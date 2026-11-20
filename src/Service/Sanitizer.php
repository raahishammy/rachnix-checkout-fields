<?php
/**
 * Sanitises submitted field values.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Hands each value to its field type's sanitiser. Sanitising cleans; it never
 * makes an invalid value valid, so the result still goes through ValidationService.
 */
final class Sanitizer {

	/**
	 * @param FieldTypeRegistry $types Field types.
	 */
	public function __construct(
		private readonly FieldTypeRegistry $types,
	) {}

	/**
	 * @param Field $field Field definition.
	 * @param mixed $raw   Submitted value, already unslashed (wp_unslash).
	 * @return string|null Clean value, or null when the field's type is not registered
	 *                     (for example a Pro type while Pro is inactive); callers skip such fields.
	 */
	public function sanitize( Field $field, mixed $raw ): ?string {
		if ( ! $this->types->has( $field->type ) ) {
			return null;
		}
		return $this->types->get( $field->type )->sanitize( $raw );
	}
}
