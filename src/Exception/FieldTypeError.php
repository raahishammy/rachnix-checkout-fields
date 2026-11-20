<?php
/**
 * Field type registry failure.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown for unknown, duplicate or malformed field type slugs. Slugs come from code, not users.
 */
final class FieldTypeError extends \LogicException {

	/**
	 * @param string $slug Type slug.
	 */
	public static function unknown( string $slug ): self {
		return new self( sprintf( 'Unknown field type "%s".', $slug ) );
	}

	/**
	 * @param string $slug Type slug.
	 */
	public static function duplicate( string $slug ): self {
		return new self( sprintf( 'Field type "%s" is already registered.', $slug ) );
	}

	/**
	 * @param string $class_name Type class.
	 */
	public static function invalid_slug( string $class_name ): self {
		return new self( sprintf( 'Field type %s has an invalid slug.', $class_name ) );
	}
}
