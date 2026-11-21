<?php
/**
 * Plugin Name: Rachnix Checkout Fields timing probe (wp-env only)
 * Description: Measures the PHP time Rachnix Checkout Fields adds to a request. Active only when the request carries the cookie rxcf_profile=1; the result is sent as the X-Rxcf-Profile header.
 *
 * Mapped into wp-content/mu-plugins by .wp-env.json. Never shipped (tests/ is in .distignore).
 *
 * What is counted:
 * - load: including the main plugin file (the hooks fired just before and after it bound it);
 * - hooks: every callback the plugin added (methods of Rachnix\CheckoutFields classes and
 *   closures in its folder), including whatever runs inside them. Nested calls are counted once.
 * The probe's own overhead is outside both timers.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Perf;

defined( 'ABSPATH' ) || exit;

if ( '1' !== ( $_COOKIE['rxcf_profile'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared with a constant, test-only switch.
	return;
}

/**
 * Timing probe.
 */
final class Profiler {

	private const CLASS_PREFIX = 'Rachnix\\CheckoutFields\\';

	private const MAIN_FILE = 'rachnix-checkout-fields/rachnix-checkout-fields.php';

	private static float $load = 0.0;

	private static float $hooks = 0.0;

	private static int $calls = 0;

	private static int $depth = 0;

	private static int $mark = 0;

	private static string $dir = '';

	/**
	 * Time per hook and callback, for the X-Rxcf-Profile-Top header.
	 *
	 * @var array<string, float>
	 */
	private static array $by_callback = array();

	/**
	 * Wrapped callback ids.
	 *
	 * @var array<string, true>
	 */
	private static array $wrapped = array();

	public static function start(): void {
		self::$dir  = wp_normalize_path( WP_PLUGIN_DIR . '/rachnix-checkout-fields/' );
		self::$mark = hrtime( true );
		ob_start(); // Holds the page, so the header can still be sent at shutdown.
		add_action( 'all', array( self::class, 'on_hook' ) );
		add_action( 'shutdown', array( self::class, 'report' ), PHP_INT_MIN ); // Before wp_ob_end_flush_all (priority 1).
	}

	/**
	 * Runs before every hook: times the main file's include and wraps the plugin's callbacks.
	 *
	 * @param mixed ...$args Hook name, then the hook's arguments.
	 */
	public static function on_hook( mixed ...$args ): void {
		$now  = hrtime( true );
		$hook = is_string( $args[0] ?? null ) ? $args[0] : '';

		if ( 'plugin_loaded' === $hook && is_string( $args[1] ?? null ) && str_ends_with( wp_normalize_path( $args[1] ), self::MAIN_FILE ) ) {
			self::$load = ( $now - self::$mark ) / 1e6;
		}

		global $wp_filter;
		if ( isset( $wp_filter[ $hook ] ) && $wp_filter[ $hook ] instanceof \WP_Hook ) {
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $entries ) {
				$changed = false;
				foreach ( $entries as $id => $entry ) {
					$key = "{$hook}|{$priority}|{$id}";
					if ( isset( self::$wrapped[ $key ] ) || ! self::is_ours( $entry['function'] ) ) {
						continue;
					}
					self::$wrapped[ $key ] = true;
					// Same array key, so remove_action() with the original callback still works.
					$entries[ $id ] = array(
						'function'      => self::timed( $entry['function'], $hook . ' ' . self::name( $entry['function'] ) ),
						'accepted_args' => $entry['accepted_args'],
					);
					$changed        = true;
				}
				if ( $changed ) {
					$wp_filter[ $hook ]->callbacks[ $priority ] = $entries;
				}
			}
		}
		self::$mark = hrtime( true );
	}

	public static function report(): void {
		if ( headers_sent() ) {
			return;
		}
		header(
			'X-Rxcf-Profile: ' . wp_json_encode(
				array(
					'load_ms'  => round( self::$load, 3 ),
					'hooks_ms' => round( self::$hooks / 1e6, 3 ),
					'total_ms' => round( self::$load + self::$hooks / 1e6, 3 ),
					'calls'    => self::$calls,
				)
			)
		);
		arsort( self::$by_callback );
		header( 'X-Rxcf-Profile-Top: ' . wp_json_encode( array_map( static fn( float $ms ): float => round( $ms, 3 ), array_slice( self::$by_callback, 0, 8 ) ) ) );
	}

	/**
	 * @param callable $original Callback.
	 * @param string   $name     Hook and callback, for the breakdown.
	 */
	private static function timed( callable $original, string $name ): \Closure {
		return static function ( mixed ...$args ) use ( $original, $name ): mixed {
			if ( self::$depth > 0 ) {
				return $original( ...$args );
			}
			++self::$depth;
			++self::$calls;
			$started = hrtime( true );
			try {
				return $original( ...$args );
			} finally {
				$spent                      = hrtime( true ) - $started;
				self::$hooks               += $spent;
				self::$by_callback[ $name ] = ( self::$by_callback[ $name ] ?? 0.0 ) + $spent / 1e6;
				--self::$depth;
			}
		};
	}

	/**
	 * @param mixed $callback Callback from WP_Hook.
	 */
	private static function name( mixed $callback ): string {
		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return str_replace( self::CLASS_PREFIX, '', $class ) . '::' . (string) $callback[1];
		}
		if ( $callback instanceof \Closure ) {
			$function = new \ReflectionFunction( $callback );
			return basename( (string) $function->getFileName() ) . ':' . $function->getStartLine();
		}
		return is_string( $callback ) ? $callback : 'callback';
	}

	/**
	 * @param mixed $callback Callback from WP_Hook.
	 */
	private static function is_ours( mixed $callback ): bool {
		if ( is_array( $callback ) && isset( $callback[0] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return str_starts_with( $class, self::CLASS_PREFIX );
		}
		if ( is_string( $callback ) ) {
			return str_starts_with( $callback, self::CLASS_PREFIX );
		}
		if ( $callback instanceof \Closure ) {
			$file = ( new \ReflectionFunction( $callback ) )->getFileName();
			return is_string( $file ) && str_starts_with( wp_normalize_path( $file ), self::$dir );
		}
		return false;
	}
}

Profiler::start();
