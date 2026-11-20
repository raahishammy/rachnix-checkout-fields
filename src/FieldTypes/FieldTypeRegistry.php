<?php
/**
 * Registry of available field types.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\FieldTypes;

use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Domain\Patterns;
use Rachnix\CheckoutFields\Exception\FieldTypeError;

defined( 'ABSPATH' ) || exit;

/**
 * Adding a field type means registering a class here; nothing else changes.
 * Add-ons register theirs on the `rxcf_register_field_types` action.
 */
final class FieldTypeRegistry {

	/**
	 * The types that ship with the free plugin, in picker order.
	 *
	 * @var list<class-string<FieldTypeInterface>>
	 */
	public const FREE_TYPES = array(
		TextField::class,
		TextareaField::class,
		EmailField::class,
		PhoneField::class,
		NumberField::class,
		PasswordField::class,
		SelectField::class,
		RadioField::class,
		CheckboxField::class,
		DateField::class,
		HiddenField::class,
	);

	/**
	 * @var array<string, FieldTypeInterface>
	 */
	private array $types = array();

	/**
	 * Registers every type in FREE_TYPES.
	 */
	public function register_free_types(): void {
		foreach ( self::FREE_TYPES as $class_name ) {
			$this->register( new $class_name() );
		}
	}

	/**
	 * @param FieldTypeInterface $type Type to add.
	 * @throws FieldTypeError When the slug is malformed or already taken.
	 */
	public function register( FieldTypeInterface $type ): void {
		$slug = $type->slug();
		if ( 1 !== preg_match( Patterns::TYPE_SLUG, $slug ) ) {
			throw FieldTypeError::invalid_slug( $type::class ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- class name from code, never printed.
		}
		if ( isset( $this->types[ $slug ] ) ) {
			throw FieldTypeError::duplicate( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- slug from code, never printed.
		}
		$this->types[ $slug ] = $type;
	}

	/**
	 * @param string $slug Type slug.
	 */
	public function has( string $slug ): bool {
		return isset( $this->types[ $slug ] );
	}

	/**
	 * @param string $slug Type slug.
	 * @throws FieldTypeError When no such type is registered.
	 */
	public function get( string $slug ): FieldTypeInterface {
		if ( ! isset( $this->types[ $slug ] ) ) {
			throw FieldTypeError::unknown( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- never printed; callers log it.
		}
		return $this->types[ $slug ];
	}

	/**
	 * All registered types, in registration order.
	 *
	 * @return list<FieldTypeInterface>
	 */
	public function all(): array {
		return array_values( $this->types );
	}

	/**
	 * Registered slugs; used as the whitelist for REST arguments.
	 *
	 * @return list<string>
	 */
	public function type_slugs(): array {
		return array_keys( $this->types );
	}
}
