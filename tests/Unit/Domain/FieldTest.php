<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class FieldTest extends TestCase {

	public const UUID = '3f2a8c1e-5b7d-4e9f-a0c2-1d3e5f7a9b0c';

	public function test_valid_field_keeps_its_values(): void {
		$field = new Field(
			uuid: self::UUID,
			section: SectionKey::core( Section::Billing ),
			key: 'billing_vat_number',
			type: 'text',
			label: 'VAT number',
			placeholder: 'GB123456789',
			settings: new FieldSettings( required: true ),
			priority: 35,
		);

		$this->assertSame( 'billing_vat_number', $field->key );
		$this->assertSame( 'billing', $field->section->value );
		$this->assertTrue( $field->settings->required );
		$this->assertTrue( $field->enabled );
		$this->assertNull( $field->id );
	}

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function hostile_identifiers(): iterable {
		yield 'uppercase uuid'      => array( strtoupper( self::UUID ), 'gift_note', 'text' );
		yield 'uuid with sql'       => array( "' OR 1=1 --", 'gift_note', 'text' );
		yield 'key with script'     => array( self::UUID, '<script>alert(1)</script>', 'text' );
		yield 'key with sql'        => array( self::UUID, 'x; DROP TABLE wp_users', 'text' );
		yield 'key starts with digit' => array( self::UUID, '1field', 'text' );
		yield 'key too long'        => array( self::UUID, 'a' . str_repeat( 'b', 64 ), 'text' );
		yield 'key one character'   => array( self::UUID, 'a', 'text' );
		yield 'type with path'      => array( self::UUID, 'gift_note', '../../evil' );
		yield 'type uppercase'      => array( self::UUID, 'gift_note', 'Text' );
	}

	#[DataProvider( 'hostile_identifiers' )]
	public function test_malformed_identifiers_are_rejected( string $uuid, string $key, string $type ): void {
		$this->expectException( InvalidFieldDefinition::class );
		new Field( $uuid, SectionKey::core( Section::Billing ), $key, $type );
	}

	public function test_overlong_label_is_rejected(): void {
		$this->expectException( InvalidFieldDefinition::class );
		new Field( self::UUID, SectionKey::core( Section::Order ), 'gift_note', 'text', label: str_repeat( 'é', 256 ) );
	}

	public function test_label_of_255_multibyte_characters_is_allowed(): void {
		$field = new Field( self::UUID, SectionKey::core( Section::Order ), 'gift_note', 'text', label: str_repeat( 'é', 255 ) );

		$this->assertSame( 255, mb_strlen( $field->label ) );
	}

	public function test_error_message_never_contains_the_rejected_value(): void {
		try {
			new Field( self::UUID, SectionKey::core( Section::Billing ), '<img src=x onerror=alert(1)>', 'text' );
			$this->fail( 'Expected an exception.' );
		} catch ( InvalidFieldDefinition $e ) {
			$this->assertStringNotContainsString( '<img', $e->getMessage() );
		}
	}

	public function test_with_persistence_adds_id_and_dates_only(): void {
		$field   = new Field( self::UUID, SectionKey::core( Section::Shipping ), 'shipping_floor', 'number', priority: 70 );
		$created = new DateTimeImmutable( '2026-11-20 10:00:00' );
		$updated = new DateTimeImmutable( '2026-11-20 11:00:00' );
		$saved   = $field->with_persistence( 42, $created, $updated );

		$this->assertSame( 42, $saved->id );
		$this->assertSame( $created, $saved->created_at );
		$this->assertSame( $updated, $saved->updated_at );
		$this->assertSame( 70, $saved->priority );
		$this->assertSame( 'shipping_floor', $saved->key );
		$this->assertNull( $field->id, 'Original is unchanged.' );
	}

	public function test_zero_id_is_rejected(): void {
		$this->expectException( InvalidFieldDefinition::class );
		new Field( self::UUID, SectionKey::core( Section::Billing ), 'gift_note', 'text', id: 0 );
	}
}
