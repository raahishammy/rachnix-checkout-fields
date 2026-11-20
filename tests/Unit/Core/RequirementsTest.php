<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Core\Dependency;
use Rachnix\CheckoutFields\Core\Requirements;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class RequirementsTest extends TestCase {

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function supported_versions(): iterable {
		yield 'exact minimums'         => array( '8.2.0', '6.5', '9.9.0' );
		yield 'short minimum versions' => array( '8.2', '6.5', '9.9' );
		yield 'current'                => array( '8.5.13', '7.1.5', '10.3.1' );
		yield 'prerelease above min'   => array( '8.6.0-dev', '7.2-beta1', '10.4.0-rc.1' );
	}

	#[DataProvider( 'supported_versions' )]
	public function test_supported_versions_pass( string $php, string $wordpress, string $woocommerce ): void {
		$this->assertSame( array(), ( new Requirements( $php, $wordpress, $woocommerce ) )->unmet() );
	}

	public function test_inactive_woocommerce_is_reported_as_missing(): void {
		$unmet = ( new Requirements( '8.3.0', '6.8', null ) )->unmet();

		$this->assertCount( 1, $unmet );
		$this->assertSame( Dependency::WooCommerce, $unmet[0]->dependency );
		$this->assertTrue( $unmet[0]->is_missing() );
	}

	public function test_each_old_version_is_reported_with_what_was_found(): void {
		$unmet = ( new Requirements( '8.1.30', '6.4.5', '9.8.5' ) )->unmet();

		$this->assertSame(
			array(
				array( Dependency::Php, '8.1.30' ),
				array( Dependency::WordPress, '6.4.5' ),
				array( Dependency::WooCommerce, '9.8.5' ),
			),
			array_map( static fn( $r ) => array( $r->dependency, $r->found ), $unmet )
		);
		$this->assertFalse( $unmet[0]->is_missing() );
	}

	/**
	 * version_compare() ranks "9.9.0-beta.1" above "9.9", so a prerelease of the
	 * minimum counts as meeting it. Deliberate: stricter normalising would also
	 * reject WordPress's own short versions such as "6.5".
	 */
	public function test_prerelease_of_the_minimum_counts_as_the_minimum(): void {
		$this->assertSame( array(), ( new Requirements( '8.2.0', '6.5', '9.9.0-beta.1' ) )->unmet() );
	}

	public function test_prerelease_below_the_minimum_is_too_old(): void {
		$unmet = ( new Requirements( '8.2.0', '6.5', '9.8.0-rc.1' ) )->unmet();

		$this->assertCount( 1, $unmet );
		$this->assertSame( Dependency::WooCommerce, $unmet[0]->dependency );
	}
}
