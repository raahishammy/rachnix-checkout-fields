<?php
/**
 * Cache of raw field rows.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the rows of `rxcf_fields` as plain arrays of strings in a transient
 * (the object cache, when the site has one). Objects are never cached, so
 * reading the cache can never instantiate a class.
 */
final class FieldCache {

	/**
	 * @param string $key Transient name.
	 */
	public function __construct(
		private readonly string $key,
	) {}

	/**
	 * @return list<array<string, mixed>>|null Null on a cache miss or unexpected content.
	 */
	public function get(): ?array {
		$rows = get_transient( $this->key );
		if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) {
			return null;
		}
		$valid = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$valid[] = $row;
		}
		return $valid;
	}

	/**
	 * @param list<array<string, mixed>> $rows Rows from the database.
	 */
	public function set( array $rows ): void {
		set_transient( $this->key, $rows, DAY_IN_SECONDS );
	}

	public function clear(): void {
		delete_transient( $this->key );
	}
}
