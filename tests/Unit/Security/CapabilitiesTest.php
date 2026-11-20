<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Security;

use Rachnix\CheckoutFields\Core\AuditLogPurger;
use Rachnix\CheckoutFields\Security\Capabilities;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class CapabilitiesTest extends TestCase {

	public function test_users_who_manage_woocommerce_get_the_capability(): void {
		$caps = ( new Capabilities() )->grant( array( 'manage_woocommerce' => true ) );

		$this->assertTrue( $caps[ Capabilities::MANAGE_FIELDS ] );
	}

	public function test_other_users_do_not(): void {
		$capabilities = new Capabilities();

		$this->assertArrayNotHasKey( Capabilities::MANAGE_FIELDS, $capabilities->grant( array( 'read' => true ) ) );
		$this->assertArrayNotHasKey( Capabilities::MANAGE_FIELDS, $capabilities->grant( array( 'manage_woocommerce' => false ) ) );
	}

	public function test_an_explicit_denial_is_respected(): void {
		$caps = ( new Capabilities() )->grant(
			array(
				'manage_woocommerce'        => true,
				Capabilities::MANAGE_FIELDS => false,
			)
		);

		$this->assertFalse( $caps[ Capabilities::MANAGE_FIELDS ] );
	}

	public function test_register_filters_user_capabilities(): void {
		$capabilities = new Capabilities();
		$capabilities->register();

		$this->assertNotFalse( has_filter( 'user_has_cap', array( $capabilities, 'grant' ) ) );
	}

	public function test_deactivation_clears_the_purge_event(): void {
		$main = (string) file_get_contents( dirname( __DIR__, 3 ) . '/rachnix-checkout-fields.php' );

		$this->assertStringContainsString( "wp_clear_scheduled_hook( '" . AuditLogPurger::HOOK . "' )", $main );
	}
}
