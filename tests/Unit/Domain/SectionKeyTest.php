<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class SectionKeyTest extends TestCase {

	public function test_core_sections_round_trip(): void {
		foreach ( Section::cases() as $section ) {
			$key = SectionKey::from_string( $section->value );

			$this->assertSame( $section, $key->section() );
			$this->assertFalse( $key->is_custom() );
		}
	}

	public function test_custom_section_round_trips(): void {
		$key = SectionKey::from_string( 'custom:' . FieldTest::UUID );

		$this->assertTrue( $key->is_custom() );
		$this->assertNull( $key->section() );
		$this->assertTrue( $key->equals( SectionKey::custom( FieldTest::UUID ) ) );
		$this->assertLessThanOrEqual( 64, strlen( $key->value ), 'Fits rxcf_fields.section.' );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function invalid_keys(): iterable {
		yield 'empty'          => array( '' );
		yield 'unknown'        => array( 'payment' );
		yield 'uppercase'      => array( 'Billing' );
		yield 'custom no uuid' => array( 'custom:' );
		yield 'custom bad id'  => array( 'custom:../../etc' );
	}

	#[DataProvider( 'invalid_keys' )]
	public function test_invalid_keys_are_rejected( string $value ): void {
		$this->expectException( InvalidFieldDefinition::class );
		SectionKey::from_string( $value );
	}
}
