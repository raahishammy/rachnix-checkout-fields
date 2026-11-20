<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Core;

use Rachnix\CheckoutFields\Core\HposCompat;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class HposCompatTest extends TestCase {

	public function test_constructor_adds_no_hooks(): void {
		$compat = new HposCompat( '/plugin.php' );

		$this->assertFalse( has_action( 'before_woocommerce_init', array( $compat, 'declare_compatibility' ) ) );
	}

	public function test_register_hooks_before_woocommerce_init(): void {
		$compat = new HposCompat( '/plugin.php' );
		$compat->register();

		$this->assertNotFalse( has_action( 'before_woocommerce_init', array( $compat, 'declare_compatibility' ) ) );
	}

	public function test_declares_hpos_and_blocks(): void {
		$this->assertSame( array( 'custom_order_tables', 'cart_checkout_blocks' ), HposCompat::FEATURES );
	}

	public function test_does_nothing_without_woocommerce(): void {
		// FeaturesUtil is not loaded in unit tests, so this must return quietly.
		( new HposCompat( '/plugin.php' ) )->declare_compatibility();

		$this->addToAssertionCount( 1 );
	}
}
