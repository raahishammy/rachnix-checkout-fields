<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

/**
 * Migrations run up, down and up again cleanly on a real database.
 */
final class MigrationsTest extends IntegrationTestCase {

	protected function setUp(): void {
		self::reset_test_schema();
	}

	protected function tearDown(): void {
		self::reset_test_schema();
	}

	public function test_up_creates_all_tables_and_records_the_version(): void {
		$this->assertTrue( self::migrator()->migrate() );

		foreach ( self::tables()->all() as $table ) {
			$this->assertTrue( self::table_exists( $table ), "{$table} exists" );
		}
		$this->assertSame( 1, self::migrator()->installed_version() );
	}

	public function test_fields_table_matches_the_spec(): void {
		self::migrator()->migrate();

		$columns = self::db()->get_col( self::db()->prepare( 'SHOW COLUMNS FROM %i', self::tables()->fields() ) );
		$this->assertSame(
			array( 'id', 'uuid', 'field_key', 'section', 'type', 'is_core', 'label', 'placeholder', 'settings', 'priority', 'enabled', 'created_by', 'created_at', 'updated_at' ),
			$columns
		);

		$indexes = array();
		foreach ( (array) self::db()->get_results( self::db()->prepare( 'SHOW INDEX FROM %i', self::tables()->fields() ) ) as $row ) {
			$indexes[ $row->Key_name ][] = $row->Column_name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column names.
		}
		$this->assertSame( array( 'id' ), $indexes['PRIMARY'] );
		$this->assertSame( array( 'uuid' ), $indexes['uuid'] );
		$this->assertSame( array( 'section', 'field_key' ), $indexes['section_field_key'] );
		$this->assertSame( array( 'section', 'enabled', 'priority' ), $indexes['section_enabled_priority'] );

		$status = self::db()->get_row( self::db()->prepare( 'SHOW TABLE STATUS LIKE %s', self::tables()->fields() ) );
		$this->assertSame( 'InnoDB', $status->Engine ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name.
		$this->assertStringStartsWith( 'utf8mb4', $status->Collation ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name.
	}

	public function test_running_up_twice_is_harmless(): void {
		self::migrator()->migrate();
		delete_option( self::VERSION_OPTION ); // Force migration 1 to run again on existing tables.

		$this->assertTrue( self::migrator()->migrate() );
		$this->assertSame( 1, self::migrator()->installed_version() );
	}

	public function test_down_drops_everything_and_up_works_again(): void {
		self::migrator()->migrate();
		self::migrator()->rollback();

		foreach ( self::tables()->all() as $table ) {
			$this->assertFalse( self::table_exists( $table ), "{$table} dropped" );
		}
		$this->assertFalse( get_option( self::VERSION_OPTION ) );

		$this->assertTrue( self::migrator()->migrate() );
		$this->assertTrue( self::table_exists( self::tables()->fields() ) );
	}

	public function test_real_plugin_tables_are_untouched(): void {
		global $wpdb;
		$real_fields_table = $wpdb->prefix . 'rxcf_fields';
		$existed_before    = self::table_exists( $real_fields_table );

		self::migrator()->migrate();
		self::migrator()->rollback();

		$this->assertSame( $existed_before, self::table_exists( $real_fields_table ) );
	}
}
