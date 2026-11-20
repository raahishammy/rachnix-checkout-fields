<?php
/**
 * Names of the plugin's tables.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves table names from one prefix, e.g. `wp_rxcf_`. Tests use their own
 * prefix, so they never touch a store's real tables.
 */
final readonly class TableNames {

	/**
	 * @param string $prefix Site prefix plus plugin prefix, e.g. "wp_rxcf_".
	 * @throws \InvalidArgumentException When the prefix holds characters not allowed in a table name.
	 */
	public function __construct(
		private string $prefix,
	) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/', $prefix ) ) {
			throw new \InvalidArgumentException( 'Invalid table prefix.' );
		}
	}

	public function fields(): string {
		return $this->prefix . 'fields';
	}

	public function sections(): string {
		return $this->prefix . 'sections';
	}

	public function audit_log(): string {
		return $this->prefix . 'audit_log';
	}

	/**
	 * @return list<string>
	 */
	public function all(): array {
		return array( $this->fields(), $this->sections(), $this->audit_log() );
	}
}
