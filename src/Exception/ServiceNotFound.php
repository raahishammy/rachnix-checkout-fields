<?php
/**
 * Container lookup failure.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a service is missing from the container or has the wrong type.
 * Messages hold class names only, so they are safe to log.
 */
final class ServiceNotFound extends \LogicException {

	/**
	 * @param string $id Requested service id.
	 */
	public static function for_id( string $id ): self {
		return new self( sprintf( 'No service is registered for "%s".', $id ) );
	}

	/**
	 * @param string $id     Requested service id.
	 * @param string $actual Class the factory returned.
	 */
	public static function wrong_type( string $id, string $actual ): self {
		return new self( sprintf( 'The factory for "%s" returned "%s".', $id, $actual ) );
	}
}
