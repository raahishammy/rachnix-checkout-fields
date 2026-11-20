<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Core;

use Brain\Monkey\Functions;
use Rachnix\CheckoutFields\Contracts\Migration;
use Rachnix\CheckoutFields\Core\Migrator;
use Rachnix\CheckoutFields\Core\TableNames;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

/**
 * Migration ordering, version bookkeeping and locking, with options held in memory.
 */
final class MigratorTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * @var list<string>
	 */
	private array $log = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options = array();
		$this->log     = array();

		Functions\when( 'get_option' )->alias( fn( string $name, mixed $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( string $name, mixed $value ): bool {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			function ( string $name, mixed $value ): bool {
				if ( array_key_exists( $name, $this->options ) ) {
					return false;
				}
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );
				return true;
			}
		);
	}

	public function test_runs_pending_migrations_in_version_order(): void {
		$migrator = $this->migrator( $this->migration( 3 ), $this->migration( 1 ), $this->migration( 2 ) );

		$this->assertTrue( $migrator->needs_migration() );
		$this->assertTrue( $migrator->migrate() );
		$this->assertSame( array( 'up 1', 'up 2', 'up 3' ), $this->log );
		$this->assertSame( 3, $migrator->installed_version() );
		$this->assertFalse( $migrator->needs_migration() );
		$this->assertArrayNotHasKey( 'test_db_version_lock', $this->options, 'Lock is released.' );
	}

	public function test_skips_applied_migrations(): void {
		$this->options['test_db_version'] = '1';
		$this->migrator( $this->migration( 1 ), $this->migration( 2 ) )->migrate();

		$this->assertSame( array( 'up 2' ), $this->log );
	}

	public function test_current_schema_does_no_work_and_takes_no_lock(): void {
		$this->options['test_db_version']      = 2;
		$this->options['test_db_version_lock'] = time();

		$this->assertTrue( $this->migrator( $this->migration( 1 ), $this->migration( 2 ) )->migrate() );
		$this->assertSame( array(), $this->log );
	}

	public function test_newer_installed_schema_is_left_alone(): void {
		$this->options['test_db_version'] = 5;

		$this->assertTrue( $this->migrator( $this->migration( 1 ) )->migrate() );
		$this->assertSame( array(), $this->log );
	}

	public function test_waits_while_another_request_holds_the_lock(): void {
		$this->options['test_db_version_lock'] = time();

		$this->assertFalse( $this->migrator( $this->migration( 1 ) )->migrate() );
		$this->assertSame( array(), $this->log );
	}

	public function test_takes_over_a_stale_lock(): void {
		$this->options['test_db_version_lock'] = time() - 3600;

		$this->assertTrue( $this->migrator( $this->migration( 1 ) )->migrate() );
		$this->assertSame( array( 'up 1' ), $this->log );
	}

	public function test_failed_migration_keeps_earlier_versions_and_releases_the_lock(): void {
		$migrator = $this->migrator( $this->migration( 1 ), $this->migration( 2, fail: true ), $this->migration( 3 ) );

		try {
			$migrator->migrate();
			$this->fail( 'Expected DatabaseError.' );
		} catch ( DatabaseError ) {
			$this->assertSame( 1, $migrator->installed_version() );
			$this->assertSame( array( 'up 1' ), $this->log );
			$this->assertArrayNotHasKey( 'test_db_version_lock', $this->options );
		}
	}

	public function test_rollback_reverts_newest_first_to_target(): void {
		$migrator = $this->migrator( $this->migration( 1 ), $this->migration( 2 ), $this->migration( 3 ) );
		$migrator->migrate();
		$this->log = array();

		$migrator->rollback( 1 );
		$this->assertSame( array( 'down 3', 'down 2' ), $this->log );
		$this->assertSame( 1, $migrator->installed_version() );

		$migrator->rollback();
		$this->assertSame( array( 'down 3', 'down 2', 'down 1' ), $this->log );
		$this->assertArrayNotHasKey( 'test_db_version', $this->options, 'Full rollback removes the version option.' );
	}

	public function test_duplicate_versions_are_rejected(): void {
		$this->expectException( \LogicException::class );
		$this->migrator( $this->migration( 1 ), $this->migration( 1 ) );
	}

	private function migrator( Migration ...$migrations ): Migrator {
		return new Migrator( \Mockery::mock( 'wpdb' ), new TableNames( 'wp_test_' ), array_values( $migrations ), 'test_db_version' );
	}

	/**
	 * @param int  $version Version.
	 * @param bool $fail    Throw from up().
	 */
	private function migration( int $version, bool $fail = false ): Migration {
		$log = &$this->log;

		return new class( $version, $fail, $log ) implements Migration {

			/**
			 * @param list<string> $log Shared call log.
			 */
			public function __construct( private int $number, private bool $fail, private array &$log ) {}

			public function version(): int {
				return $this->number;
			}

			public function up( \wpdb $db, TableNames $tables ): void {
				if ( $this->fail ) {
					throw DatabaseError::failed( 'test', 'boom' );
				}
				$this->log[] = "up {$this->number}";
			}

			public function down( \wpdb $db, TableNames $tables ): void {
				$this->log[] = "down {$this->number}";
			}
		};
	}
}
