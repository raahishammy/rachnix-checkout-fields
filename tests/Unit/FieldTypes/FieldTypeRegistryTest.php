<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\FieldTypes;

use Rachnix\CheckoutFields\Exception\FieldTypeError;
use Rachnix\CheckoutFields\FieldTypes\AbstractFieldType;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\FieldTypes\TextField;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class FieldTypeRegistryTest extends TestCase {

	public function test_free_types_register_all_eleven_in_order(): void {
		$registry = new FieldTypeRegistry();
		$registry->register_free_types();

		$this->assertSame(
			array( 'text', 'textarea', 'email', 'phone', 'number', 'password', 'select', 'radio', 'checkbox', 'date', 'hidden' ),
			$registry->type_slugs()
		);
		$this->assertCount( 11, $registry->all() );
	}

	public function test_get_returns_the_registered_type(): void {
		$registry = new FieldTypeRegistry();
		$text     = new TextField();
		$registry->register( $text );

		$this->assertTrue( $registry->has( 'text' ) );
		$this->assertSame( $text, $registry->get( 'text' ) );
	}

	public function test_an_add_on_type_registers_without_changing_the_registry(): void {
		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$registry->register( $this->type( 'color' ) );

		$this->assertTrue( $registry->has( 'color' ) );
		$this->assertSame( 'color', $registry->type_slugs()[11] );
	}

	public function test_extension_runs_once_on_first_lookup_not_on_construction(): void {
		$calls    = 0;
		$registry = new FieldTypeRegistry(
			function ( FieldTypeRegistry $r ) use ( &$calls ): void {
				++$calls;
				$r->register( $this->type( 'color' ) );
			}
		);
		$registry->register_free_types();
		$this->assertSame( 0, $calls, 'Not on construction or registration.' );

		$this->assertTrue( $registry->has( 'color' ) );
		$registry->all();
		$registry->type_slugs();
		$this->assertSame( 1, $calls );
	}

	public function test_core_is_never_a_field_type(): void {
		$this->expectException( FieldTypeError::class );
		( new FieldTypeRegistry() )->register( $this->type( 'core' ) );
	}

	public function test_duplicate_slug_is_rejected(): void {
		$registry = new FieldTypeRegistry();
		$registry->register( new TextField() );

		$this->expectException( FieldTypeError::class );
		$registry->register( $this->type( 'text' ) );
	}

	public function test_malformed_slug_is_rejected(): void {
		$this->expectException( FieldTypeError::class );
		( new FieldTypeRegistry() )->register( $this->type( 'Bad Slug' ) );
	}

	public function test_unknown_slug_throws(): void {
		$registry = new FieldTypeRegistry();

		$this->assertFalse( $registry->has( 'nope' ) );
		$this->expectException( FieldTypeError::class );
		$registry->get( 'nope' );
	}

	/**
	 * @param string $slug Slug of the test type.
	 */
	private function type( string $slug ): AbstractFieldType {
		return new class( $slug ) extends AbstractFieldType {

			public function __construct( private readonly string $type_slug ) {}

			public function slug(): string {
				return $this->type_slug;
			}

			public function label(): string {
				return 'Test';
			}

			public function description(): string {
				return 'Test type.';
			}

			public function icon(): string {
				return 'admin-generic';
			}
		};
	}
}
