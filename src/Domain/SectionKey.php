<?php
/**
 * Reference to a core or custom section.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * The value stored in `rxcf_fields.section`: a core section slug, or `custom:{section_uuid}`.
 */
final readonly class SectionKey {

	private const CUSTOM_PREFIX = 'custom:';

	/**
	 * @param string $value Stored form.
	 */
	private function __construct(
		public string $value,
	) {}

	/**
	 * @param Section $section Core section.
	 */
	public static function core( Section $section ): self {
		return new self( $section->value );
	}

	/**
	 * @param string $section_uuid UUID of a row in `rxcf_sections`.
	 * @throws InvalidFieldDefinition When the UUID is malformed.
	 */
	public static function custom( string $section_uuid ): self {
		if ( 1 !== preg_match( Patterns::UUID, $section_uuid ) ) {
			throw InvalidFieldDefinition::invalid( 'section' );
		}
		return new self( self::CUSTOM_PREFIX . $section_uuid );
	}

	/**
	 * Parses the stored form.
	 *
	 * @param string $value Stored form.
	 * @throws InvalidFieldDefinition When it is neither a core section nor a valid custom reference.
	 */
	public static function from_string( string $value ): self {
		$section = Section::tryFrom( $value );
		if ( null !== $section ) {
			return self::core( $section );
		}
		if ( str_starts_with( $value, self::CUSTOM_PREFIX ) ) {
			return self::custom( substr( $value, strlen( self::CUSTOM_PREFIX ) ) );
		}
		throw InvalidFieldDefinition::invalid( 'section' );
	}

	/**
	 * The core section, or null for a custom section.
	 */
	public function section(): ?Section {
		return Section::tryFrom( $this->value );
	}

	public function is_custom(): bool {
		return null === $this->section();
	}

	/**
	 * @param self $other Other key.
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}
}
