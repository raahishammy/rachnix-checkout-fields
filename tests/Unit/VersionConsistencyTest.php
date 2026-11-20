<?php
/**
 * Guards against the plugin header and the code drifting apart.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit;

use Rachnix\CheckoutFields\Core\Dependency;
use Rachnix\CheckoutFields\Plugin;

final class VersionConsistencyTest extends TestCase {

	/**
	 * @var array<string, string>
	 */
	private static array $header = array();

	public static function setUpBeforeClass(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/rachnix-checkout-fields.php' );
		preg_match_all( '/^\s*\*\s*([A-Za-z ]+):\s*(.+)$/m', $source, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			self::$header[ trim( $match[1] ) ] = trim( $match[2] );
		}
	}

	public function test_header_version_matches_plugin_constant(): void {
		$this->assertSame( Plugin::VERSION, self::$header['Version'] ?? null );
	}

	public function test_header_minimums_match_runtime_checks(): void {
		$this->assertSame( Dependency::Php->minimum(), self::$header['Requires PHP'] ?? null );
		$this->assertSame( Dependency::WordPress->minimum(), self::$header['Requires at least'] ?? null );
		$this->assertSame( Dependency::WooCommerce->minimum(), self::$header['WC requires at least'] ?? null );
	}

	public function test_text_domain_matches_slug(): void {
		$this->assertSame( 'rachnix-checkout-fields', self::$header['Text Domain'] ?? null );
	}
}
