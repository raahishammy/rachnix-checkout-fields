<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Checkout;

use Rachnix\CheckoutFields\Tests\Unit\TestCase;

/**
 * Enforces the spec's stylesheet rules: under 4 KB, and zero specificity so any theme
 * rule wins. The only exceptions are :root variables and Custom mode, where the store
 * owner deliberately overrides the theme.
 */
final class StylesheetTest extends TestCase {

	private const ALLOWED_PREFIXES = array( ':root', 'body.rxcf-style-custom', 'body.rxcf-labels-side', 'body.rxcf-theme-' );

	private static function css(): string {
		return (string) file_get_contents( dirname( __DIR__, 3 ) . '/assets/css/checkout.css' );
	}

	public function test_stylesheet_is_under_4_kb(): void {
		$this->assertLessThan( 4096, strlen( self::css() ) );
	}

	public function test_every_selector_has_zero_specificity_or_is_an_allowed_exception(): void {
		$selectors = self::selectors();
		$this->assertGreaterThan( 10, count( $selectors ), 'The parser found the rules.' );

		foreach ( $selectors as $selector ) {
			if ( $this->starts_with_allowed( $selector ) ) {
				continue;
			}
			$this->assertSame( '', trim( self::strip_where( $selector ), " \t\n>+~" ), "Not zero-specificity: {$selector}" );
		}
	}

	public function test_custom_mode_rules_are_scoped_to_the_body_class(): void {
		foreach ( self::selectors() as $selector ) {
			if ( str_contains( $selector, 'rxcf-style-custom' ) || str_contains( $selector, 'rxcf-labels-side' ) ) {
				$this->assertTrue( $this->starts_with_allowed( $selector ), $selector );
			}
		}
	}

	public function test_theme_fixes_only_target_one_theme_each(): void {
		foreach ( self::selectors() as $selector ) {
			if ( str_contains( $selector, 'rxcf-theme-' ) ) {
				$this->assertMatchesRegularExpression( '/^body\.rxcf-theme-[a-z0-9-]+ /', $selector );
			}
		}
	}

	public function test_inherits_theme_typography_and_reads_theme_colour_tokens(): void {
		$css = self::css();

		$this->assertStringContainsString( 'font:inherit', $css );
		$this->assertStringContainsString( 'color:inherit', $css );
		$this->assertStringContainsString( 'currentColor', $css );
		$this->assertStringContainsString( 'var(--wp--preset--color--primary,var(--wp--preset--color--accent,#2271b1))', $css );
	}

	/**
	 * Every top-level selector of every rule, @media wrappers removed.
	 *
	 * @return list<string>
	 */
	private static function selectors(): array {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', self::css() );
		$css = (string) preg_replace( '#@media[^{]*\{#', '', $css );
		preg_match_all( '#([^{}]+)\{[^{}]*\}#', $css, $matches );

		$selectors = array();
		foreach ( $matches[1] as $group ) {
			foreach ( self::split_top_level( $group ) as $selector ) {
				$selector = trim( $selector );
				if ( '' !== $selector ) {
					$selectors[] = $selector;
				}
			}
		}
		return $selectors;
	}

	/**
	 * Splits "a, :is(b, c)" on top-level commas only.
	 *
	 * @param string $group Selector list.
	 * @return list<string>
	 */
	private static function split_top_level( string $group ): array {
		$parts = array();
		$depth = 0;
		$part  = '';
		foreach ( str_split( $group ) as $char ) {
			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char ) {
				--$depth;
			}
			if ( ',' === $char && 0 === $depth ) {
				$parts[] = $part;
				$part    = '';
				continue;
			}
			$part .= $char;
		}
		$parts[] = $part;
		return $parts;
	}

	/**
	 * Removes every balanced :where(...) group.
	 *
	 * @param string $selector Selector.
	 */
	private static function strip_where( string $selector ): string {
		while ( false !== ( $start = strpos( $selector, ':where(' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$depth = 0;
			$end   = $start + strlen( ':where' );
			$len   = strlen( $selector );
			for ( ; $end < $len; $end++ ) {
				if ( '(' === $selector[ $end ] ) {
					++$depth;
				} elseif ( ')' === $selector[ $end ] && 0 === --$depth ) {
					break;
				}
			}
			$selector = substr( $selector, 0, $start ) . substr( $selector, $end + 1 );
		}
		return $selector;
	}

	private function starts_with_allowed( string $selector ): bool {
		foreach ( self::ALLOWED_PREFIXES as $prefix ) {
			if ( str_starts_with( $selector, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
