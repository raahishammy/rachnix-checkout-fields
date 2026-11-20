<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Domain;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldCollection;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class FieldCollectionTest extends TestCase {

	private FieldCollection $fields;

	protected function setUp(): void {
		parent::setUp();
		$this->fields = new FieldCollection(
			array(
				$this->field( 1, Section::Billing, 'billing_phone', 100 ),
				$this->field( 2, Section::Shipping, 'shipping_floor', 10 ),
				$this->field( 3, Section::Billing, 'billing_company', 30, enabled: false ),
				$this->field( 4, Section::Billing, 'billing_alpha', 30 ),
			)
		);
	}

	public function test_sorts_by_priority_then_key(): void {
		$this->assertSame(
			array( 'shipping_floor', 'billing_alpha', 'billing_company', 'billing_phone' ),
			array_map( static fn( Field $f ) => $f->key, $this->fields->to_list() )
		);
	}

	public function test_filters_by_section_and_enabled(): void {
		$billing = $this->fields->in_section( SectionKey::core( Section::Billing ) );

		$this->assertCount( 3, $billing );
		$this->assertCount( 2, $billing->enabled() );
		$this->assertCount( 4, $this->fields, 'Filtering returns a new collection.' );
	}

	public function test_finds_by_uuid_and_by_key(): void {
		$this->assertSame( 'billing_phone', $this->fields->find( $this->uuid( 1 ) )?->key );
		$this->assertNull( $this->fields->find( $this->uuid( 9 ) ) );
		$this->assertSame( 2, $this->fields->find_by_key( SectionKey::core( Section::Shipping ), 'shipping_floor' )?->id );
		$this->assertNull( $this->fields->find_by_key( SectionKey::core( Section::Billing ), 'shipping_floor' ) );
	}

	public function test_is_iterable(): void {
		$this->assertCount( 4, iterator_to_array( $this->fields ) );
	}

	/**
	 * @param int     $n        Number used for id and uuid.
	 * @param Section $section  Section.
	 * @param string  $key      Field key.
	 * @param int     $priority Priority.
	 * @param bool    $enabled  Enabled.
	 */
	private function field( int $n, Section $section, string $key, int $priority, bool $enabled = true ): Field {
		return new Field( $this->uuid( $n ), SectionKey::core( $section ), $key, 'text', priority: $priority, enabled: $enabled, id: $n );
	}

	/**
	 * @param int $n Number.
	 */
	private function uuid( int $n ): string {
		return sprintf( '00000000-0000-4000-8000-%012d', $n );
	}
}
