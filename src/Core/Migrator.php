<?php
/**
 * Runs schema migrations.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

use Rachnix\CheckoutFields\Contracts\Migration;
use Rachnix\CheckoutFields\Exception\DatabaseError;

defined( 'ABSPATH' ) || exit;

/**
 * Brings each site's schema to the latest version. The installed version is a
 * per-site autoloaded option, so the check is free on every request and each
 * site of a multisite network migrates itself the first time it loads.
 */
final class Migrator {

	/** Seconds after which a lock left by a crashed request is ignored. */
	private const LOCK_TTL = 600;

	/**
	 * @var array<int, Migration> Keyed by version, ascending.
	 */
	private array $migrations = array();

	/**
	 * @param \wpdb           $db             Database.
	 * @param TableNames      $tables         Table names.
	 * @param list<Migration> $migrations     All migrations, in any order.
	 * @param string          $version_option Option that stores the installed version.
	 * @throws \LogicException When two migrations share a version or a version is not positive.
	 */
	public function __construct(
		private readonly \wpdb $db,
		private readonly TableNames $tables,
		array $migrations,
		private readonly string $version_option,
	) {
		foreach ( $migrations as $migration ) {
			$version = $migration->version();
			if ( $version < 1 || isset( $this->migrations[ $version ] ) ) {
				throw new \LogicException( 'Migration versions must be positive and unique.' );
			}
			$this->migrations[ $version ] = $migration;
		}
		ksort( $this->migrations );
	}

	public function installed_version(): int {
		$version = get_option( $this->version_option, 0 );
		return is_numeric( $version ) ? (int) $version : 0;
	}

	public function latest_version(): int {
		return array_key_last( $this->migrations ) ?? 0;
	}

	public function needs_migration(): bool {
		return $this->installed_version() < $this->latest_version();
	}

	/**
	 * Applies every pending migration in order.
	 *
	 * @return bool True when the schema is current; false when another request is migrating right now.
	 * @throws DatabaseError When a migration fails. Versions applied before it stay recorded.
	 */
	public function migrate(): bool {
		if ( ! $this->needs_migration() ) {
			return true;
		}
		if ( ! $this->acquire_lock() ) {
			return false;
		}
		try {
			foreach ( $this->migrations as $version => $migration ) {
				if ( $version > $this->installed_version() ) {
					$migration->up( $this->db, $this->tables );
					update_option( $this->version_option, $version, true );
				}
			}
		} finally {
			$this->release_lock();
		}
		return true;
	}

	/**
	 * Reverts applied migrations, newest first, down to `$target`. 0 removes everything.
	 *
	 * @param int $target Version to end at.
	 * @return bool False when another request is migrating right now.
	 * @throws DatabaseError When a migration cannot be reverted.
	 */
	public function rollback( int $target = 0 ): bool {
		if ( ! $this->acquire_lock() ) {
			return false;
		}
		try {
			$versions = array_reverse( array_keys( $this->migrations ) );
			foreach ( $versions as $index => $version ) {
				if ( $version <= $target || $version > $this->installed_version() ) {
					continue;
				}
				$this->migrations[ $version ]->down( $this->db, $this->tables );
				$previous = $versions[ $index + 1 ] ?? 0;
				if ( 0 === $previous ) {
					delete_option( $this->version_option );
				} else {
					update_option( $this->version_option, $previous, true );
				}
			}
		} finally {
			$this->release_lock();
		}
		return true;
	}

	private function acquire_lock(): bool {
		$lock = $this->lock_option();
		if ( add_option( $lock, time(), '', false ) ) {
			return true;
		}
		$since = get_option( $lock, 0 );
		if ( is_numeric( $since ) && time() - (int) $since > self::LOCK_TTL ) {
			delete_option( $lock );
			return add_option( $lock, time(), '', false );
		}
		return false;
	}

	private function release_lock(): void {
		delete_option( $this->lock_option() );
	}

	private function lock_option(): string {
		return $this->version_option . '_lock';
	}
}
