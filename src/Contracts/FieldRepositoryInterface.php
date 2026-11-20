<?php
/**
 * Contract for field storage.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Contracts;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldCollection;
use Rachnix\CheckoutFields\Exception\DatabaseError;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes field definitions.
 */
interface FieldRepositoryInterface {

	/**
	 * Every field, enabled or not. Served from cache when possible.
	 */
	public function all(): FieldCollection;

	/**
	 * @param string $uuid Field UUID.
	 */
	public function find( string $uuid ): ?Field;

	/**
	 * Inserts a field without an id, or updates the row with its id.
	 *
	 * @param Field $field Field to store.
	 * @return Field The stored field, with id and timestamps.
	 * @throws DatabaseError When the write fails, for example on a duplicate key in the same section.
	 */
	public function save( Field $field ): Field;

	/**
	 * @param string $uuid Field UUID.
	 * @return bool Whether a row was deleted.
	 * @throws DatabaseError When the delete fails.
	 */
	public function delete( string $uuid ): bool;
}
