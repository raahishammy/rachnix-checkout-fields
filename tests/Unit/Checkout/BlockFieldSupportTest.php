<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Checkout;

use Brain\Monkey\Filters;
use Rachnix\CheckoutFields\Checkout\Blocks\BlockFieldSupport;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class BlockFieldSupportTest extends TestCase {

	public function test_maps_every_free_type(): void {
		$support  = new BlockFieldSupport( date_supported: true );
		$expected = array(
			'text'     => 'text',
			'textarea' => null,
			'email'    => 'text',
			'phone'    => 'text',
			'number'   => null,
			'password' => null,
			'select'   => 'select',
			'radio'    => null,
			'checkbox' => 'checkbox',
			'date'     => 'date',
			'hidden'   => null,
		);

		$actual = array();
		foreach ( FieldTypeRegistry::FREE_TYPES as $class_name ) {
			$slug            = ( new $class_name() )->slug();
			$actual[ $slug ] = $support->native_type( $slug );
		}
		$this->assertSame( $expected, $actual );
	}

	public function test_date_needs_woocommerce_11_2(): void {
		$this->assertNull( ( new BlockFieldSupport( date_supported: false ) )->native_type( 'date' ) );
		$this->assertFalse( ( new BlockFieldSupport( date_supported: false ) )->is_shown( 'date' ) );
		// Without WooCommerce loaded, detection finds no DateFieldType class.
		$this->assertFalse( ( new BlockFieldSupport() )->date_supported() );
	}

	public function test_email_and_phone_get_autofill_hints(): void {
		$support = new BlockFieldSupport();

		$this->assertSame( 'email', $support->autocomplete( 'email' ) );
		$this->assertSame( 'tel', $support->autocomplete( 'phone' ) );
		$this->assertNull( $support->autocomplete( 'text' ) );
	}

	public function test_an_add_on_can_declare_that_it_renders_a_type(): void {
		Filters\expectApplied( 'rxcf_block_checkout_renders_type' )->once()->with( false, 'textarea' )->andReturn( true );

		$this->assertTrue( ( new BlockFieldSupport() )->is_shown( 'textarea' ) );
	}

	public function test_unknown_types_are_not_shown(): void {
		$this->assertFalse( ( new BlockFieldSupport() )->is_shown( 'color' ) );
	}
}
