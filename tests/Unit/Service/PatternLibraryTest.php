<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Service;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Domain\Patterns;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

/**
 * Every curated pattern must be safe to run on hostile input.
 */
final class PatternLibraryTest extends TestCase {

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function ids(): iterable {
		foreach ( ( new PatternLibrary() )->ids() as $id ) {
			yield $id => array( $id );
		}
	}

	#[DataProvider( 'ids' )]
	public function test_pattern_is_anchored_and_matches_its_example( string $id ): void {
		Functions\stubTranslationFunctions();
		$library = new PatternLibrary();
		$regex   = (string) $library->regex( $id );

		$this->assertMatchesRegularExpression( Patterns::PATTERN_ID, $id );
		$this->assertStringStartsWith( '/^', $regex );
		$this->assertMatchesRegularExpression( '#\$/[a-z]*D[a-z]*$#', $regex, 'Ends with $ and the D modifier.' );
		$this->assertSame( 1, preg_match( $regex, (string) $library->example( $id ) ), 'Matches its own example.' );
		$this->assertNotNull( $library->label( $id ) );
	}

	#[DataProvider( 'ids' )]
	public function test_trailing_newline_does_not_match( string $id ): void {
		$library = new PatternLibrary();

		$this->assertSame( 0, preg_match( (string) $library->regex( $id ), $library->example( $id ) . "\n" ) );
	}

	#[DataProvider( 'ids' )]
	public function test_pattern_stays_fast_on_adversarial_input( string $id ): void {
		$library = new PatternLibrary();
		$regex   = (string) $library->regex( $id );

		// Long runs of characters the pattern accepts, then one it rejects: the classic ReDoS shape.
		$attacks = array(
			str_repeat( 'a', 50000 ) . '!',
			str_repeat( '1', 50000 ) . 'x',
			str_repeat( 'a-', 25000 ) . '!',
			str_repeat( "a '", 16000 ) . '!',
			str_repeat( 'é', 25000 ) . '!',
		);

		foreach ( $attacks as $attack ) {
			$start  = hrtime( true );
			$result = preg_match( $regex, $attack );
			$ms     = ( hrtime( true ) - $start ) / 1e6;

			$this->assertNotFalse( $result, preg_last_error_msg() );
			$this->assertLessThan( 50, $ms, "{$id} took {$ms} ms" );
		}
	}

	public function test_unknown_id_returns_null(): void {
		Functions\stubTranslationFunctions();
		$library = new PatternLibrary();

		$this->assertNull( $library->regex( 'nope' ) );
		$this->assertNull( $library->example( 'nope' ) );
		$this->assertNull( $library->label( 'nope' ) );
	}
}
