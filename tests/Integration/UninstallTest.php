<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Core\Uninstaller;
use Rachnix\CheckoutFields\Repository\SettingsRepository;

/**
 * Deleting the plugin removes its data only when the owner opted in, and never order data.
 * Everything here uses rxcftest_ names, so the store's real tables and options stay.
 */
final class UninstallTest extends IntegrationTestCase {

	private const OPTION     = 'rxcftest_settings';
	private const CRON       = 'rxcftest_purge_audit_log';
	private const CAPABILITY = 'rxcftest_manage_fields';

	protected function setUp(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
		delete_option( self::OPTION );
		set_transient( 'rxcftest_field_rows', array( 'cached' ), HOUR_IN_SECONDS );
		set_transient( 'rxcfkeep_other_plugin', 'kept', HOUR_IN_SECONDS );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		get_role( 'administrator' )?->add_cap( self::CAPABILITY );
		get_role( 'editor' )?->add_cap( self::CAPABILITY, false ); // An explicit deny from a role editor.
	}

	protected function tearDown(): void {
		self::reset_test_schema();
		delete_option( self::OPTION );
		delete_transient( 'rxcftest_field_rows' );
		delete_transient( 'rxcfkeep_other_plugin' );
		wp_clear_scheduled_hook( self::CRON );
		get_role( 'administrator' )?->remove_cap( self::CAPABILITY );
		get_role( 'editor' )?->remove_cap( self::CAPABILITY );
	}

	public function test_nothing_is_removed_unless_the_owner_opted_in(): void {
		update_option( self::OPTION, array( 'wizard_completed' => true ) );

		$this->assertFalse( $this->uninstaller()->uninstall_site() );

		foreach ( self::tables()->all() as $table ) {
			$this->assertTrue( self::table_exists( $table ), $table );
		}
		$this->assertSame( array( 'wizard_completed' => true ), get_option( self::OPTION ) );
		$this->assertSame( array( 'cached' ), get_transient( 'rxcftest_field_rows' ) );
		$this->assertNotFalse( wp_next_scheduled( self::CRON ) );
		$this->assertTrue( get_role( 'administrator' )?->has_cap( self::CAPABILITY ) );
	}

	public function test_opted_in_removes_tables_settings_transients_event_and_role_grants(): void {
		( new SettingsRepository( self::OPTION ) )->save_delete_data_on_uninstall( true );

		$this->assertTrue( $this->uninstaller()->uninstall_site() );
		wp_cache_flush(); // The transients were deleted by SQL; read them from the database.

		foreach ( self::tables()->all() as $table ) {
			$this->assertFalse( self::table_exists( $table ), $table );
		}
		$this->assertFalse( get_option( self::OPTION ) );
		$this->assertFalse( get_option( self::VERSION_OPTION ) );
		$this->assertFalse( get_transient( 'rxcftest_field_rows' ) );
		$this->assertSame( 'kept', get_transient( 'rxcfkeep_other_plugin' ), 'Other prefixes stay.' );
		$this->assertFalse( wp_next_scheduled( self::CRON ) );
		$this->assertArrayNotHasKey( self::CAPABILITY, get_role( 'administrator' )->capabilities ?? array() );
		$this->assertArrayNotHasKey( self::CAPABILITY, get_role( 'editor' )->capabilities ?? array() );
	}

	public function test_order_values_are_kept(): void {
		( new SettingsRepository( self::OPTION ) )->save_delete_data_on_uninstall( true );
		$order = wc_create_order();
		$order->update_meta_data( '_rxcf_rxcftest_gift', 'Kept' );
		$order->update_meta_data( '_wc_other/rachnix/rxcftest_gift', 'Kept too' );
		$order->save();

		try {
			$this->uninstaller()->uninstall_site();

			$reloaded = wc_get_order( $order->get_id() );
			$this->assertInstanceOf( \WC_Order::class, $reloaded );
			$this->assertSame( 'Kept', $reloaded->get_meta( '_rxcf_rxcftest_gift' ) );
			$this->assertSame( 'Kept too', $reloaded->get_meta( '_wc_other/rachnix/rxcftest_gift' ) );
		} finally {
			$order->delete( true );
		}
	}

	public function test_the_setting_key_matches_the_settings_repository(): void {
		$this->assertSame( SettingsRepository::DELETE_DATA_KEY, Uninstaller::SETTING );
	}

	private function uninstaller(): Uninstaller {
		return new Uninstaller(
			'rxcftest_',
			array( self::OPTION, self::VERSION_OPTION, self::VERSION_OPTION . '_lock' ),
			'rxcftest_',
			self::CRON,
			self::CAPABILITY
		);
	}
}
