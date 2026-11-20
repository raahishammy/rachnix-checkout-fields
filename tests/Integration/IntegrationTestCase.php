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
	 * Creates a throwaway user with a role. Delete it with delete_test_user().
	 *
	 * @param string $role Role slug, e.g. "customer" or "shop_manager".
	 */
	protected static function create_test_user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'rxcftest_' . $role . '_' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => 'rxcftest_' . wp_generate_password( 8, false ) . '@example.invalid',
				'role'       => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( 'Could not create test user: ' . esc_html( $id->get_error_message() ) );
		}
		return $id;
	}

	protected static function delete_test_user( int $user_id ): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( is_multisite() ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			wpmu_delete_user( $user_id );
		} else {
			wp_delete_user( $user_id );
		}
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
