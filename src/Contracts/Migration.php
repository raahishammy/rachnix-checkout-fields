<?php
/**
 * Contract for a versioned schema change.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Contracts;

use Rachnix\CheckoutFields\Core\TableNames;
use Rachnix\CheckoutFields\Exception\DatabaseError;

defined( 'ABSPATH' ) || exit;

/**
 * One step of the database schema. Released migrations are never edited; add a new one instead.
 */
interface Migration {

	/**
	 * Schema version this migration brings the database to. Positive and unique.
	 */
	public function version(): int;

	/**
	 * Applies the change. Must be safe to run again on an already migrated database.
	 *
	 * @param \wpdb      $db     Database.
	 * @param TableNames $tables Table names.
	 * @throws DatabaseError When the change could not be applied.
	 */
	public function up( \wpdb $db, TableNames $tables ): void;

	/**
	 * Reverts the change.
	 *
	 * @param \wpdb      $db     Database.
	 * @param TableNames $tables Table names.
	 * @throws DatabaseError When the change could not be reverted.
	 */
	public function down( \wpdb $db, TableNames $tables ): void;
}
