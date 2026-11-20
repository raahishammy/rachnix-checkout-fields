<?php
/**
 * Daily removal of old audit log entries.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps 90 days of audit history. The event is cleared on deactivation (main plugin file).
 */
final class AuditLogPurger implements Hookable {

	/** Keep in sync with the deactivation hook in rachnix-checkout-fields.php. */
	public const HOOK = 'rxcf_purge_audit_log';

	public const RETENTION_DAYS = 90;

	/**
	 * @param AuditLogRepository $audit Audit log.
	 */
	public function __construct(
		private readonly AuditLogRepository $audit,
	) {}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'purge' ) );
		add_action( 'init', array( $this, 'schedule' ) );
	}

	public function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public function purge(): void {
		$this->audit->purge_older_than( self::RETENTION_DAYS );
	}
}
