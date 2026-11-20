<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Security\Logger;

/**
 * FieldRepository against real tables (with the test prefix).
 */
final class FieldRepositoryTest extends IntegrationTestCase {

	public static function setUpBeforeClass(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
	}

	public static function tearDownAfterClass(): void {
		self::reset_test_schema();
	}

	protected function setUp(): void {
		self::db()->query( self::db()->prepare( 'TRUNCATE TABLE %i', self::tables()->fields() ) );
		delete_transient( self::CACHE_KEY );
	}

	public function test_insert_then_read_back(): void {
		$repo  = $this->repository();
		$saved = $repo->save(
			$this->field(
				'billing',
				'billing_vat',
				settings: new FieldSettings( required: true, options: array( new FieldOption( 'a', 'Ä label' ) ) )
			)
		);

		$this->assertNotNull( $saved->id );
		$this->assertNotNull( $saved->created_at );

		$loaded = $this->repository()->find( $saved->uuid ); // Fresh instance: reads the table, not memory.
		$this->assertNotNull( $loaded );
		$this->assertSame( 'billing_vat', $loaded->key );
		$this->assertTrue( $loaded->settings->required );
		$this->assertSame( 'Ä label', $loaded->settings->options[0]->label );
		$this->assertSame( $saved->id, $loaded->id );
	}

	public function test_update_changes_the_row_and_keeps_created_at(): void {
		$repo  = $this->repository();
		$saved = $repo->save( $this->field( 'order', 'gift_note', priority: 20 ) );

		$changed = new Field(
			uuid: $saved->uuid,
			section: $saved->section,
			key: $saved->key,
			type: 'textarea',
			label: 'Gift note',
			priority: 5,
			enabled: false,
			id: $saved->id,
			created_at: $saved->created_at,
		);
		$repo->save( $changed );

		$loaded = $this->repository()->find( $saved->uuid );
		$this->assertNotNull( $loaded );
		$this->assertSame( 'textarea', $loaded->type );
		$this->assertSame( 5, $loaded->priority );
		$this->assertFalse( $loaded->enabled );
		$this->assertEquals( $saved->created_at, $loaded->created_at );
		$this->assertCount( 1, $this->repository()->all() );
	}

	public function test_same_key_in_same_section_is_rejected(): void {
		$repo = $this->repository();
		$repo->save( $this->field( 'billing', 'billing_vat' ) );
		$repo->save( $this->field( 'shipping', 'billing_vat' ) ); // Same key, other section: fine.

		self::db()->suppress_errors( true );
		try {
			$this->expectException( DatabaseError::class );
			$repo->save( $this->field( 'billing', 'billing_vat' ) );
		} finally {
			self::db()->suppress_errors( false );
		}
	}

	public function test_delete(): void {
		$repo  = $this->repository();
		$saved = $repo->save( $this->field( 'billing', 'billing_vat' ) );

		$this->assertTrue( $repo->delete( $saved->uuid ) );
		$this->assertFalse( $repo->delete( $saved->uuid ), 'Second delete finds nothing.' );
		$this->assertNull( $this->repository()->find( $saved->uuid ) );
	}

	public function test_reads_are_cached_and_writes_clear_the_cache(): void {
		$this->repository()->save( $this->field( 'billing', 'billing_vat' ) );
		$this->repository()->all();
		$this->assertIsArray( get_transient( self::CACHE_KEY ), 'Rows cached after first read.' );

		// A row written behind the repository's back stays invisible while the cache is warm...
		$this->insert_raw_row( 'order', 'sneaky', '{}' );
		$this->assertCount( 1, $this->repository()->all() );

		// ...and appears once a save clears the cache.
		$this->repository()->save( $this->field( 'shipping', 'shipping_floor' ) );
		$this->assertCount( 3, $this->repository()->all() );
	}

	public function test_cache_holds_only_plain_rows(): void {
		$this->repository()->save( $this->field( 'billing', 'billing_vat' ) );
		$this->repository()->all();

		$cached = get_transient( self::CACHE_KEY );
		$this->assertIsArray( $cached );
		array_walk_recursive(
			$cached,
			function ( mixed $value ): void {
				$this->assertFalse( is_object( $value ), 'No objects in the cache.' );
			}
		);
	}

	public function test_damaged_rows_are_skipped_not_fatal(): void {
		$this->repository()->save( $this->field( 'billing', 'billing_good' ) );
		$this->insert_raw_row( 'billing', 'bad_json', '{not json' );
		$this->insert_raw_row( 'nowhere', 'bad_section', '{}' );
		$this->insert_raw_row( 'billing', 'BAD KEY', '{}' );

		$keys = array_map( static fn( Field $f ) => $f->key, $this->repository()->all()->to_list() );
		$this->assertSame( array( 'billing_good' ), $keys );
	}

	public function test_hostile_text_is_stored_as_data(): void {
		$label = "Robert'); DROP TABLE wp_users; -- <script>alert(1)</script>";
		$saved = $this->repository()->save( $this->field( 'billing', 'billing_note', label: $label ) );

		$this->assertSame( $label, $this->repository()->find( $saved->uuid )?->label );
		$this->assertTrue( self::table_exists( self::db()->users ) );
	}

	private function repository(): FieldRepository {
		return new FieldRepository( self::db(), self::tables(), new FieldCache( self::CACHE_KEY ), new Logger() );
	}

	/**
	 * @param string        $section  Core section slug.
	 * @param string        $key      Field key.
	 * @param int           $priority Priority.
	 * @param string        $label    Label.
	 * @param FieldSettings $settings Settings.
	 */
	private function field( string $section, string $key, int $priority = 10, string $label = 'Label', FieldSettings $settings = new FieldSettings() ): Field {
		return new Field( wp_generate_uuid4(), SectionKey::core( Section::from( $section ) ), $key, 'text', label: $label, settings: $settings, priority: $priority );
	}

	private function insert_raw_row( string $section, string $key, string $settings ): void {
		self::db()->insert(
			self::tables()->fields(),
			array(
				'uuid'       => wp_generate_uuid4(),
				'field_key'  => $key,
				'section'    => $section,
				'type'       => 'text',
				'settings'   => $settings,
				'created_at' => '2026-11-20 00:00:00',
				'updated_at' => '2026-11-20 00:00:00',
			)
		);
	}
}
