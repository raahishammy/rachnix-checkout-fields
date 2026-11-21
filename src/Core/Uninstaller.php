<?php
/**
 * What deleting the plugin removes.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs from uninstall.php, without the plugin booted.
 *
 * Nothing is removed unless the store owner turned on "Delete all data when the plugin
 * is deleted" (Tools tab). Then the plugin's tables, settings, transients, scheduled
 * event and any role grants of its capability go. Order meta is never touched: field
 * values on orders are the store's records, and WooCommerce keeps block checkout values.
 *
 * On multisite each site is checked with its own setting. Tests pass their own names,
 * so a store's real data is never touched.
 */
final class Uninstaller {

	/** Key inside the settings option, the same as SettingsRepository::DELETE_DATA_KEY. */
	public const SETTING = 'delete_data_on_uninstall';

	/**
	 * @param string       $table_prefix     Plugin prefix after the site prefix, e.g. "rxcf_".
	 * @param list<string> $options          Option names; the first holds the settings.
	 * @param string       $transient_prefix Transients starting with this are removed.
	 * @param string       $cron_hook        Scheduled event to clear.
	 * @param string       $capability       Capability to take from every role.
	 */
	public function __construct(
		private readonly string $table_prefix = 'rxcf_',
		private readonly array $options = array( 'rxcf_settings', 'rxcf_db_version', 'rxcf_db_version_lock' ),
		private readonly string $transient_prefix = 'rxcf_',
		private readonly string $cron_hook = 'rxcf_purge_audit_log',
		private readonly string $capability = 'rxcf_manage_fields',
	) {}

	/**
	 * Uninstalls on every site of a network, or on the one site.
	 *
	 * @return int Sites whose data was removed.
	 */
	public function run(): int {
		if ( ! is_multisite() ) {
			return $this->uninstall_site() ? 1 : 0;
		}
		$removed = 0;
		$offset  = 0;
		do {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				$removed += $this->uninstall_site() ? 1 : 0;
				restore_current_blog();
			}
			$offset += 100;
			$full    = 100 === count( $site_ids );
		} while ( $full );
		return $removed;
	}

	/**
	 * Removes the current site's data if its owner opted in.
	 *
	 * @return bool Whether data was removed.
	 */
	public function uninstall_site(): bool {
		if ( ! $this->opted_in() ) {
			return false;
		}
		global $wpdb;

		$tables = new TableNames( $wpdb->prefix . $this->table_prefix );
		foreach ( $tables->all() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall drops the plugin's own tables; nothing to cache.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}

		foreach ( $this->options as $option ) {
			delete_option( $option );
		}

		// Transients in the options table (with a persistent object cache they expire on their own).
		foreach ( array( '_transient_', '_transient_timeout_' ) as $kind ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes the plugin's own transients by prefix; WordPress has no API for that.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $kind . $this->transient_prefix ) . '%' ) );
		}

		wp_clear_scheduled_hook( $this->cron_hook );

		foreach ( wp_roles()->role_objects as $role ) {
			if ( $role->has_cap( $this->capability ) || array_key_exists( $this->capability, $role->capabilities ) ) {
				$role->remove_cap( $this->capability );
			}
		}
		return true;
	}

	private function opted_in(): bool {
		$settings = get_option( $this->options[0] ?? '', array() );
		return is_array( $settings ) && true === ( $settings[ self::SETTING ] ?? false );
	}
}
