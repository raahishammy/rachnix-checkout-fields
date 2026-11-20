<?php
/**
 * Validates submitted field values.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\ValidationError;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Security\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a sanitised value against its field, in this order:
 * 1. length cap of the type (before any regex runs);
 * 2. required, for an empty value;
 * 3. the type's own format check (number, date, option list...);
 * 4. the type's built-in rules plus the configured rules the type allows.
 */
final class ValidationService {

	/**
	 * @param FieldTypeRegistry $types    Field types.
	 * @param PatternLibrary    $patterns Curated patterns.
	 * @param Logger            $logger   Error log.
	 */
	public function __construct(
		private readonly FieldTypeRegistry $types,
		private readonly PatternLibrary $patterns,
		private readonly Logger $logger,
	) {}

	/**
	 * @param Field  $field Field definition.
	 * @param string $value Value returned by Sanitizer.
	 * @return list<ValidationError> Empty when the value is acceptable. A field of an
	 *                               unregistered type returns no errors; it is not rendered.
	 */
	public function validate( Field $field, string $value ): array {
		if ( ! $this->types->has( $field->type ) ) {
			return array();
		}
		$type = $this->types->get( $field->type );

		$max = $type->max_length();
		if ( mb_strlen( $value ) > $max ) {
			return array( new ValidationError( $field->key, ValidationErrorCode::TooLong, max: (float) $max ) );
		}

		if ( '' === $value ) {
			return $field->settings->required && $type->can_be_required()
				? array( new ValidationError( $field->key, ValidationErrorCode::Required ) )
				: array();
		}

		$errors = $type->value_errors( $value, $field );
		if ( array() !== $errors ) {
			return $errors; // A malformed value gets one clear message, not a pile of rule errors.
		}

		foreach ( $this->rules_for( $field, $type ) as $rule ) {
			$error = $this->check( $rule, $field, $value );
			if ( null !== $error ) {
				$errors[] = $error;
			}
		}
		return $errors;
	}

	/**
	 * Built-in rules first, then configured rules the type allows. Rules the type does
	 * not allow (stale settings, tampered imports) are ignored.
	 *
	 * @param Field              $field Field definition.
	 * @param FieldTypeInterface $type  Its type.
	 * @return list<ValidationRule>
	 */
	private function rules_for( Field $field, FieldTypeInterface $type ): array {
		$allowed    = $type->allowed_rules();
		$configured = array_filter(
			$field->settings->validation,
			static fn( ValidationRule $rule ): bool => in_array( $rule->type, $allowed, true )
		);
		return array_merge( $type->built_in_rules(), array_values( $configured ) );
	}

	/**
	 * @param ValidationRule $rule  Rule.
	 * @param Field          $field Field definition.
	 * @param string         $value Non-empty sanitised value within the length cap.
	 */
	private function check( ValidationRule $rule, Field $field, string $value ): ?ValidationError {
		$key = $field->key;

		return match ( $rule->type ) {
			ValidationRuleType::Email       => false === is_email( $value ) ? new ValidationError( $key, ValidationErrorCode::InvalidEmail ) : null,
			ValidationRuleType::Phone       => $this->is_phone( $value ) ? null : new ValidationError( $key, ValidationErrorCode::InvalidPhone ),
			ValidationRuleType::Length      => $this->check_length( $rule, $key, $value ),
			ValidationRuleType::NumberRange => $this->check_range( $rule, $key, $value ),
			ValidationRuleType::Pattern     => $this->check_pattern( $rule, $key, $value ),
		};
	}

	/**
	 * @param ValidationRule $rule  Length rule.
	 * @param string         $key   Field key.
	 * @param string         $value Value.
	 */
	private function check_length( ValidationRule $rule, string $key, string $value ): ?ValidationError {
		$length = mb_strlen( $value );
		if ( null !== $rule->min && $length < $rule->min ) {
			return new ValidationError( $key, ValidationErrorCode::TooShort, min: $rule->min );
		}
		if ( null !== $rule->max && $length > $rule->max ) {
			return new ValidationError( $key, ValidationErrorCode::TooLong, max: $rule->max );
		}
		return null;
	}

	/**
	 * @param ValidationRule $rule  Range rule.
	 * @param string         $key   Field key.
	 * @param string         $value Value; the number type has already checked its format.
	 */
	private function check_range( ValidationRule $rule, string $key, string $value ): ?ValidationError {
		if ( ! is_numeric( $value ) ) {
			return new ValidationError( $key, ValidationErrorCode::InvalidNumber );
		}
		$number = (float) $value;
		if ( ( null !== $rule->min && $number < $rule->min ) || ( null !== $rule->max && $number > $rule->max ) ) {
			return new ValidationError( $key, ValidationErrorCode::OutOfRange, $rule->min, $rule->max );
		}
		return null;
	}

	/**
	 * Unknown pattern ids are skipped. A regex engine error rejects the value (fail closed).
	 *
	 * @param ValidationRule $rule  Pattern rule.
	 * @param string         $key   Field key.
	 * @param string         $value Value.
	 */
	private function check_pattern( ValidationRule $rule, string $key, string $value ): ?ValidationError {
		$id    = $rule->pattern_id;
		$regex = null === $id ? null : $this->patterns->regex( $id );
		if ( null === $id || null === $regex ) {
			return null;
		}

		$result = preg_match( $regex, $value );
		if ( false === $result ) {
			$this->logger->error(
				'Validation pattern failed to run.',
				array(
					'pattern' => $id,
					'error'   => preg_last_error_msg(),
				)
			);
		}

		return 1 === $result
			? null
			: new ValidationError( $key, ValidationErrorCode::PatternMismatch, hint: $this->patterns->example( $id ) );
	}

	/**
	 * Digits with optional leading +, spaces, dots, dashes and parentheses; 6 to 15 digits (E.164 maximum).
	 *
	 * @param string $value Value.
	 */
	private function is_phone( string $value ): bool {
		if ( 1 !== preg_match( '/^\+?[0-9 ().-]+$/D', $value ) ) {
			return false;
		}
		$digits = strlen( (string) preg_replace( '/[^0-9]/', '', $value ) );
		return $digits >= 6 && $digits <= 15;
	}
}
