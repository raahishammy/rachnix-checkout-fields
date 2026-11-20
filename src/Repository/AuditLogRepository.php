<?php
/**
 * Writes to `rxcf_audit_log`.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Rachnix\CheckoutFields\Core\TableNames;
use Rachnix\CheckoutFields\Domain\AuditAction;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Security\RequestContext;

defined( 'ABSPATH' ) || exit;

/**
 * Who changed which field settings, and how. Holds field definitions only, never
 * customer data, and the IP only as a salted hash.
 */
final class AuditLogRepository {

	private const DATE_FORMAT = 'Y-m-d H:i:s';

	/**
	 * @param \wpdb          $db      Database.
	 * @param TableNames     $tables  Table names.
	 * @param RequestContext $request Current request.
	 * @param Logger         $logger  Error log.
	 */
	public function __construct(
		private readonly \wpdb $db,
		private readonly TableNames $tables,
		private readonly RequestContext $request,
		private readonly Logger $logger,
	) {}

	/**
	 * Records a change. Only keys whose value changed are kept. A failed write is
	 * logged and does not undo or block the change itself.
	 *
	 * @param AuditAction               $action      What happened.
	 * @param string                    $object_type "field" or "section".
	 * @param string                    $object_id   Field UUID or section id (at most 36 characters).
	 * @param array<string, mixed>|null $before      Definition before the change.
	 * @param array<string, mixed>|null $after       Definition after the change.
	 * @param int                       $user_id     Acting user.
	 */
	public function record( AuditAction $action, string $object_type, string $object_id, ?array $before, ?array $after, int $user_id ): void {
		$changes = self::diff( $before, $after );
		if ( null !== $before && null !== $after && array() === $changes['after'] ) {
			return; // Nothing changed.
		}

		$json = wp_json_encode( $changes );
		$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$written = false !== $json && false !== $this->db->insert(
			$this->tables->audit_log(),
			array(
				'user_id'     => $user_id,
				'action'      => $action->value,
				'object_type' => $object_type,
				'object_id'   => substr( $object_id, 0, 36 ),
				'changes'     => $json,
				'ip_hash'     => $this->request->ip_hash(),
				'created_at'  => $now->format( self::DATE_FORMAT ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $written ) {
			$this->logger->error(
				'Could not write the audit log.',
				array(
					'action'   => $action->value,
					'db_error' => $this->db->last_error,
				)
			);
		}
	}

	/**
	 * Deletes entries older than `$days` days.
	 *
	 * @param int $days Retention in days.
	 * @return int Rows deleted.
	 */
	public function purge_older_than( int $days ): int {
		$cutoff = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( "-{$days} days" );
		$sql    = $this->db->prepare( 'DELETE FROM %i WHERE created_at < %s', $this->tables->audit_log(), $cutoff->format( self::DATE_FORMAT ) );
		if ( null === $sql ) {
			return 0;
		}
		$deleted = $this->db->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared on the line above.
		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Newest entries first; for tests and a future admin screen.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function latest( int $limit = 20 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT user_id, action, object_type, object_id, changes, ip_hash, created_at FROM %i ORDER BY id DESC LIMIT %d', $this->tables->audit_log(), $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * @param array<string, mixed>|null $before Before.
	 * @param array<string, mixed>|null $after  After.
	 * @return array{before: array<string, mixed>, after: array<string, mixed>}
	 */
	private static function diff( ?array $before, ?array $after ): array {
		if ( null === $before || null === $after ) {
			return array(
				'before' => $before ?? array(),
				'after'  => $after ?? array(),
			);
		}
		$changed = array();
		foreach ( array_keys( $before + $after ) as $key ) {
			if ( ( $before[ $key ] ?? null ) !== ( $after[ $key ] ?? null ) ) {
				$changed[] = $key;
			}
		}
		$keys = array_flip( $changed );
		return array(
			'before' => array_intersect_key( $before, $keys ),
			'after'  => array_intersect_key( $after, $keys ),
		);
	}
}
