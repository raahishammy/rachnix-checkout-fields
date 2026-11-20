<?php
/**
 * One validation rule attached to a field.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Rule data only. What each rule means is decided by ValidationService.
 */
final readonly class ValidationRule {

	/**
	 * @param ValidationRuleType $type       Kind of rule.
	 * @param float|null         $min        Minimum value (number_range) or length (length).
	 * @param float|null         $max        Maximum value (number_range) or length (length).
	 * @param string|null        $pattern_id Id from the curated pattern list (pattern).
	 */
	public function __construct(
		public ValidationRuleType $type,
		public ?float $min = null,
		public ?float $max = null,
		public ?string $pattern_id = null,
	) {}

	/**
	 * Reads stored data. Returns null for an unknown rule.
	 *
	 * @param mixed $data Decoded JSON.
	 */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) || ! is_string( $data['rule'] ?? null ) ) {
			return null;
		}
		$type = ValidationRuleType::tryFrom( $data['rule'] );
		if ( null === $type ) {
			return null;
		}
		$pattern = $data['pattern'] ?? null;

		return new self(
			$type,
			self::number( $data['min'] ?? null ),
			self::number( $data['max'] ?? null ),
			is_string( $pattern ) && 1 === preg_match( Patterns::PATTERN_ID, $pattern ) ? $pattern : null,
		);
	}

	/**
	 * @return array<string, string|float>
	 */
	public function to_array(): array {
		return array_filter(
			array(
				'rule'    => $this->type->value,
				'min'     => $this->min,
				'max'     => $this->max,
				'pattern' => $this->pattern_id,
			),
			static fn( $value ): bool => null !== $value
		);
	}

	/**
	 * @param mixed $value Decoded JSON value.
	 */
	private static function number( mixed $value ): ?float {
		return is_int( $value ) || is_float( $value ) ? (float) $value : null;
	}
}
