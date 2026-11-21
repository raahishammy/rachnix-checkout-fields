<?php
/**
 * Field storage in `rxcf_fields`.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Core\TableNames;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldCollection;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;
use Rachnix\CheckoutFields\Security\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * The one class that queries `rxcf_fields`. Every query is prepared; table and
 * column names are fixed in code. Reads come from a cache that every write clears.
 */
final class FieldRepository implements FieldRepositoryInterface {

	private const DATE_FORMAT = 'Y-m-d H:i:s';

	private const COLUMNS = 'id, uuid, field_key, section, type, is_core, label, placeholder, settings, priority, enabled, created_by, created_at, updated_at';

	private ?FieldCollection $loaded = null;

	/** Open transactions. While one is open, reads skip the cache and never write it. */
	private int $transaction_depth = 0;

	/** Draft fields shown instead of the stored ones for this request only (preview). */
	private ?FieldCollection $preview = null;

	/**
	 * @param \wpdb      $db     Database.
	 * @param TableNames $tables Table names.
	 * @param FieldCache $cache  Row cache.
	 * @param Logger     $logger Error log.
	 */
	public function __construct(
		private readonly \wpdb $db,
		private readonly TableNames $tables,
		private readonly FieldCache $cache,
		private readonly Logger $logger,
	) {}

	/**
	 * Every field. If the table cannot be read, logs the error and returns no fields,
	 * so the checkout falls back to WooCommerce's defaults instead of breaking.
	 */
	public function all(): FieldCollection {
		if ( null !== $this->preview && 0 === $this->transaction_depth ) {
			return $this->preview;
		}
		if ( null !== $this->loaded ) {
			return $this->loaded;
		}

		$in_transaction = $this->transaction_depth > 0;
		$rows           = $in_transaction ? null : $this->cache->get();
		if ( null === $rows ) {
			$rows = $this->fetch_rows();
			if ( null === $rows ) {
				return new FieldCollection();
			}
			if ( ! $in_transaction ) {
				// Never cache uncommitted rows: a persistent object cache is not rolled back.
				$this->cache->set( $rows );
			}
		}

		$fields = array();
		foreach ( $rows as $row ) {
			$field = $this->hydrate( $row );
			if ( null !== $field ) {
				$fields[] = $field;
			}
		}

		$this->loaded = new FieldCollection( $fields );
		return $this->loaded;
	}

	public function find( string $uuid ): ?Field {
		return $this->all()->find( $uuid );
	}

	public function save( Field $field ): Field {
		// Whole seconds, as DATETIME stores them, so the returned field equals the stored one.
		$now      = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$now      = $now->setTime( (int) $now->format( 'H' ), (int) $now->format( 'i' ), (int) $now->format( 's' ) );
		$settings = wp_json_encode( $field->settings->to_array() );
		if ( false === $settings ) {
			throw DatabaseError::failed( 'encode field settings', '' );
		}

		$columns = array(
			'field_key'   => array( $field->key, '%s' ),
			'section'     => array( $field->section->value, '%s' ),
			'type'        => array( $field->type, '%s' ),
			'is_core'     => array( (int) $field->is_core, '%d' ),
			'label'       => array( $field->label, '%s' ),
			'placeholder' => array( $field->placeholder, '%s' ),
			'settings'    => array( $settings, '%s' ),
			'priority'    => array( $field->priority, '%d' ),
			'enabled'     => array( (int) $field->enabled, '%d' ),
			'updated_at'  => array( $now->format( self::DATE_FORMAT ), '%s' ),
		);

		if ( null === $field->id ) {
			$columns += array(
				'uuid'       => array( $field->uuid, '%s' ),
				'created_by' => array( $field->created_by, '%d' ),
				'created_at' => array( $now->format( self::DATE_FORMAT ), '%s' ),
			);
			$result   = $this->db->insert( $this->tables->fields(), $this->values( $columns ), array_column( $columns, 1 ) );
			if ( false === $result ) {
				throw DatabaseError::failed( 'insert field', $this->db->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged only.
			}
			$saved = $field->with_persistence( (int) $this->db->insert_id, $now, $now );
		} else {
			$result = $this->db->update( $this->tables->fields(), $this->values( $columns ), array( 'id' => $field->id ), array_column( $columns, 1 ), array( '%d' ) );
			if ( false === $result ) {
				throw DatabaseError::failed( 'update field', $this->db->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged only.
			}
			$saved = $field->with_persistence( $field->id, $field->created_at ?? $now, $now );
		}

		$this->invalidate();
		return $saved;
	}

	public function delete( string $uuid ): bool {
		$result = $this->db->delete( $this->tables->fields(), array( 'uuid' => $uuid ), array( '%s' ) );
		if ( false === $result ) {
			throw DatabaseError::failed( 'delete field', $this->db->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged only.
		}
		$this->invalidate();
		return $result > 0;
	}

	/**
	 * Our tables and the audit log are InnoDB, so the whole batch commits or rolls back.
	 * Caches are cleared on both outcomes, because the object cache is not transactional.
	 *
	 * @template T
	 * @param callable(): T $work   Changes to apply.
	 * @param bool          $commit False to roll back even on success (dry run).
	 * @return T
	 * @throws \Throwable Whatever `$work` threw, after rolling back.
	 */
	public function transaction( callable $work, bool $commit = true ): mixed {
		if ( $this->transaction_depth > 0 ) {
			if ( ! $commit ) {
				throw new \LogicException( 'A dry run cannot be nested inside another transaction.' );
			}
			return $work(); // Already inside one: MySQL does not nest transactions.
		}

		$this->db->query( 'START TRANSACTION' );
		++$this->transaction_depth;
		$this->loaded = null;
		try {
			$result = $work();
			$this->db->query( $commit ? 'COMMIT' : 'ROLLBACK' );
			return $result;
		} catch ( \Throwable $e ) {
			$this->db->query( 'ROLLBACK' );
			throw $e;
		} finally {
			--$this->transaction_depth;
			$this->invalidate();
		}
	}

	/**
	 * Shows draft fields instead of the stored ones, for the rest of this request (preview).
	 *
	 * @param FieldCollection $fields Draft fields.
	 */
	public function show_preview( FieldCollection $fields ): void {
		$this->preview = $fields;
	}

	/**
	 * @param array<string, array{0: mixed, 1: string}> $columns Column => [value, format].
	 * @return array<string, mixed>
	 */
	private function values( array $columns ): array {
		return array_map( static fn( array $column ): mixed => $column[0], $columns );
	}

	/**
	 * @return list<array<string, mixed>>|null Null when the query fails.
	 */
	private function fetch_rows(): ?array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT ' . self::COLUMNS . ' FROM %i ORDER BY priority ASC, id ASC', $this->tables->fields() ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- COLUMNS is a constant.
			ARRAY_A
		);
		if ( ! is_array( $rows ) || '' !== $this->db->last_error ) {
			$this->logger->error( 'Could not read checkout fields.', array( 'db_error' => $this->db->last_error ) );
			return null;
		}
		return array_values( $rows );
	}

	/**
	 * Builds a Field from a row. Damaged rows are logged and skipped, never fatal.
	 *
	 * @param array<string, mixed> $row Database row.
	 */
	private function hydrate( array $row ): ?Field {
		try {
			$settings = json_decode( $this->text( $row, 'settings' ), true, 64, JSON_THROW_ON_ERROR );

			return new Field(
				uuid: $this->text( $row, 'uuid' ),
				section: SectionKey::from_string( $this->text( $row, 'section' ) ),
				key: $this->text( $row, 'field_key' ),
				type: $this->text( $row, 'type' ),
				label: $this->text( $row, 'label' ),
				placeholder: $this->text( $row, 'placeholder' ),
				settings: FieldSettings::from_array( is_array( $settings ) ? $settings : array() ),
				priority: $this->integer( $row, 'priority' ),
				enabled: 1 === $this->integer( $row, 'enabled' ),
				is_core: 1 === $this->integer( $row, 'is_core' ),
				created_by: $this->integer( $row, 'created_by' ),
				id: $this->integer( $row, 'id' ),
				created_at: $this->date( $row, 'created_at' ),
				updated_at: $this->date( $row, 'updated_at' ),
			);
		} catch ( InvalidFieldDefinition | \JsonException $e ) {
			$this->logger->error(
				'Skipped a damaged checkout field row.',
				array(
					'id'     => $this->integer( $row, 'id' ),
					'reason' => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * @param array<string, mixed> $row    Database row.
	 * @param string               $column Column name.
	 */
	private function text( array $row, string $column ): string {
		$value = $row[ $column ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param array<string, mixed> $row    Database row.
	 * @param string               $column Column name.
	 */
	private function integer( array $row, string $column ): int {
		$value = $row[ $column ] ?? 0;
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * @param array<string, mixed> $row    Database row.
	 * @param string               $column Column name.
	 */
	private function date( array $row, string $column ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( self::DATE_FORMAT, $this->text( $row, $column ), new DateTimeZone( 'UTC' ) );
		return false === $date ? null : $date;
	}

	private function invalidate(): void {
		$this->loaded = null;
		$this->cache->clear();
	}
}
