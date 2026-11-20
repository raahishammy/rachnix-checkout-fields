<?php
/**
 * Malformed field data.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a field or section is built from invalid data.
 * Messages name the property only, never the rejected value, so they are safe to log.
 */
final class InvalidFieldDefinition extends \InvalidArgumentException {

	/**
	 * @param string $property Property name.
	 */
	public static function invalid( string $property ): self {
		return new self( sprintf( 'Invalid field %s.', $property ) );
	}

	/**
	 * @param string $property Property name.
	 * @param int    $max      Maximum length in characters.
	 */
	public static function too_long( string $property, int $max ): self {
		return new self( sprintf( 'Field %s is longer than %d characters.', $property, $max ) );
	}
}
