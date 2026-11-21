<?php
/**
 * Defaults shared by field types.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\ValidationError;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode;

defined( 'ABSPATH' ) || exit;

/**
 * A plain single-line input: no choices, placeholder allowed, can be required,
 * no extra rules, up to 255 characters. Types override only what differs.
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

	public function built_in_rules(): array {
		return array();
	}

	public function max_length(): int {
		return 255;
	}

	/**
	 * Single line: strips tags (and script/style content), line breaks, tabs,
	 * percent-encoded octets and invalid UTF-8; trims.
	 *
	 * @param mixed $raw Submitted value.
	 */
	public function sanitize( mixed $raw ): string {
		return sanitize_text_field( $this->scalar_to_string( $raw ) );
	}

	public function value_errors( string $value, Field $field ): array {
		return array();
	}

	public function format_value( string $value, Field $field ): string {
		return $value;
	}

	/**
	 * For choice types: the chosen option's label, or the raw value if the option was removed since.
	 *
	 * @param string $value Stored value.
	 * @param Field  $field Field definition.
	 */
	protected function choice_label( string $value, Field $field ): string {
		foreach ( $field->settings->options as $option ) {
			if ( $option->value === $value ) {
				return $option->label;
			}
		}
		return $value;
	}

	/**
	 * Strings and numbers pass through; arrays, objects, booleans and null become ''.
	 * Tampered requests such as `field[]=x` therefore never reach a sanitiser.
	 *
	 * @param mixed $raw Submitted value.
	 */
	protected function scalar_to_string( mixed $raw ): string {
		if ( is_string( $raw ) ) {
			return $raw;
		}
		return is_int( $raw ) || is_float( $raw ) ? (string) $raw : '';
	}

	/**
	 * For choice types: the value must be one of the field's option values, exactly.
	 *
	 * @param string $value Sanitised value.
	 * @param Field  $field Field definition.
	 * @return list<ValidationError>
	 */
	protected function choice_errors( string $value, Field $field ): array {
		$allowed = array_map( static fn( FieldOption $option ): string => $option->value, $field->settings->options );

		return in_array( $value, $allowed, true )
			? array()
			: array( new ValidationError( $field->key, ValidationErrorCode::InvalidChoice ) );
	}
}
