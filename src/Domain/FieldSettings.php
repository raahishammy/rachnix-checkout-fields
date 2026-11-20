<?php
/**
 * Field options stored as JSON in `rxcf_fields.settings`.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Everything about a field that is not its own table column.
 */
final readonly class FieldSettings {

	/**
	 * @param string                $default_value Pre-filled value.
	 * @param bool                  $required      Customer must fill it in.
	 * @param Width                 $width         Full or half row.
	 * @param list<string>          $css_classes   Extra CSS classes on the field wrapper.
	 * @param list<FieldOption>     $options       Choices, for types that support them.
	 * @param list<ValidationRule>  $validation    Extra validation rules.
	 * @param DisplayFlags          $display       Where the value is shown after checkout.
	 * @param array<mixed>          $extensions    Data owned by add-ons (for example Pro conditions), kept as is.
	 */
	public function __construct(
		public string $default_value = '',
		public bool $required = false,
		public Width $width = Width::Full,
		public array $css_classes = array(),
		public array $options = array(),
		public array $validation = array(),
		public DisplayFlags $display = new DisplayFlags(),
		public array $extensions = array(),
	) {}

	/**
	 * Reads stored data. Unknown keys are dropped and malformed entries fall back to defaults,
	 * so a damaged row can never stop the checkout from loading.
	 *
	 * @param array<mixed> $data Decoded JSON.
	 */
	public static function from_array( array $data ): self {
		$width   = is_string( $data['width'] ?? null ) ? Width::tryFrom( $data['width'] ) : null;
		$default = $data['default'] ?? '';

		return new self(
			is_scalar( $default ) ? (string) $default : '',
			true === ( $data['required'] ?? false ),
			$width ?? Width::Full,
			self::css_classes( $data['css_classes'] ?? null ),
			self::list_of( $data['options'] ?? null, FieldOption::from_array( ... ) ),
			self::list_of( $data['validation'] ?? null, ValidationRule::from_array( ... ) ),
			DisplayFlags::from_array( is_array( $data['display'] ?? null ) ? $data['display'] : array() ),
			is_array( $data['extensions'] ?? null ) ? $data['extensions'] : array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'default'     => $this->default_value,
			'required'    => $this->required,
			'width'       => $this->width->value,
			'css_classes' => $this->css_classes,
			'options'     => array_map( static fn( FieldOption $option ) => $option->to_array(), $this->options ),
			'validation'  => array_map( static fn( ValidationRule $rule ) => $rule->to_array(), $this->validation ),
			'display'     => $this->display->to_array(),
			'extensions'  => $this->extensions,
		);
	}

	/**
	 * @param mixed $value Decoded JSON.
	 * @return list<string>
	 */
	private static function css_classes( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$classes = array();
		foreach ( $value as $class ) {
			if ( is_string( $class ) && 1 === preg_match( Patterns::CSS_CLASS, $class ) ) {
				$classes[] = $class;
			}
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * @template T of object
	 * @param mixed                 $value   Decoded JSON.
	 * @param callable(mixed): ?T   $factory Builds one entry, or returns null to drop it.
	 * @return list<T>
	 */
	private static function list_of( mixed $value, callable $factory ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$items = array();
		foreach ( $value as $entry ) {
			$item = $factory( $entry );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}
}
