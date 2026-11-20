<?php
/**
 * Base class for tests against a real database.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Core\Migrations\CreateTables;
use Rachnix\CheckoutFields\Core\Migrator;
use Rachnix\CheckoutFields\Core\TableNames;

/**
 * Uses the `rxcftest_` table prefix and option names, never the plugin's real ones.
 */
abstract class IntegrationTestCase extends \PHPUnit\Framework\TestCase {

	protected const VERSION_OPTION = 'rxcftest_db_version';
	protected const CACHE_KEY      = 'rxcftest_field_rows';

	protected static function db(): \wpdb {
		global $wpdb;
		return $wpdb;
	}

	protected static function tables(): TableNames {
		return new TableNames( self::db()->prefix . 'rxcftest_' );
	}

	protected static function migrator(): Migrator {
		return new Migrator( self::db(), self::tables(), array( new CreateTables() ), self::VERSION_OPTION );
	}

	protected static function table_exists( string $table ): bool {
		$db = self::db();
		return $table === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) );
	}

	/**
	 * Removes leftovers of an earlier, interrupted run.
	 */
	protected static function reset_test_schema(): void {
		foreach ( self::tables()->all() as $table ) {
			self::db()->query( self::db()->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
		delete_option( self::VERSION_OPTION );
		delete_option( self::VERSION_OPTION . '_lock' );
		delete_transient( self::CACHE_KEY );
	}
}
