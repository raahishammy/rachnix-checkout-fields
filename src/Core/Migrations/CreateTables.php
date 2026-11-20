<?php
/**
 * Schema version 1: the fields, sections and audit log tables.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core\Migrations;

use Rachnix\CheckoutFields\Contracts\Migration;
use Rachnix\CheckoutFields\Core\TableNames;
use Rachnix\CheckoutFields\Exception\DatabaseError;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the three tables with dbDelta(). Released: do not edit, add a new migration instead.
 *
 * `section` is VARCHAR(64), not 32 as first specified: "custom:{uuid}" is 43 characters.
 */
final class CreateTables implements Migration {

	public function version(): int {
		return 1;
	}

	public function up( \wpdb $db, TableNames $tables ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$options = 'ENGINE=InnoDB ' . $db->get_charset_collate();

		dbDelta(
			array(
				"CREATE TABLE `{$tables->fields()}` (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  field_key varchar(64) NOT NULL,
  section varchar(64) NOT NULL,
  type varchar(32) NOT NULL,
  is_core tinyint(1) NOT NULL DEFAULT 0,
  label varchar(255) NOT NULL DEFAULT '',
  placeholder varchar(255) NOT NULL DEFAULT '',
  settings longtext NOT NULL,
  priority int(11) NOT NULL DEFAULT 10,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY section_field_key (section,field_key),
  KEY section_enabled_priority (section,enabled,priority)
) {$options};",
				"CREATE TABLE `{$tables->sections()}` (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  slug varchar(64) NOT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  step smallint(5) unsigned NOT NULL DEFAULT 1,
  position int(11) NOT NULL DEFAULT 0,
  settings longtext NOT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY step_position (step,position)
) {$options};",
				"CREATE TABLE `{$tables->audit_log()}` (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(32) NOT NULL,
  object_type varchar(32) NOT NULL,
  object_id char(36) NOT NULL,
  changes longtext NOT NULL,
  ip_hash char(64) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY object (object_type,object_id)
) {$options};",
			)
		);

		foreach ( $tables->all() as $table ) {
			if ( ! $this->table_exists( $db, $table ) ) {
				throw DatabaseError::failed( "create {$table}", $db->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- table name and DB error, logged only.
			}
		}
	}

	public function down( \wpdb $db, TableNames $tables ): void {
		foreach ( $tables->all() as $table ) {
			$sql = $db->prepare( 'DROP TABLE IF EXISTS %i', $table );
			if ( null === $sql ) {
				throw DatabaseError::failed( "prepare drop {$table}", '' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- table name, logged only.
			}
			$db->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared on the line above.
			if ( $this->table_exists( $db, $table ) ) {
				throw DatabaseError::failed( "drop {$table}", $db->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- table name and DB error, logged only.
			}
		}
	}

	/**
	 * @param \wpdb  $db    Database.
	 * @param string $table Full table name.
	 */
	private function table_exists( \wpdb $db, string $table ): bool {
		return $table === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) );
	}
}
