<?php
/**
 * Database failure.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a query or schema change fails. Messages hold the operation and
 * the database error only; field values and customer data are never included.
 */
final class DatabaseError extends \RuntimeException {

	/**
	 * @param string $operation What was attempted, e.g. "insert field".
	 * @param string $db_error  $wpdb->last_error.
	 */
	public static function failed( string $operation, string $db_error ): self {
		return new self( sprintf( 'Database operation "%s" failed: %s', $operation, '' !== $db_error ? $db_error : 'unknown error' ) );
	}
}
