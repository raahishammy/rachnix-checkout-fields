<?php
/**
 * Bootstrap for integration tests: loads a real WordPress (XAMPP locally, wp-env in CI).
 * Tests use their own table prefix and option names, so a store's real data is never touched.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

$rxcf_wp_load = getenv( 'WP_LOAD_PATH' );
if ( false === $rxcf_wp_load || '' === $rxcf_wp_load ) {
	// tests/Integration -> tests -> plugin -> plugins -> wp-content -> site root.
	$rxcf_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
}
if ( ! is_readable( $rxcf_wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$rxcf_wp_load}. Set WP_LOAD_PATH." . PHP_EOL );
	exit( 1 );
}

// WordPress expects these on a web request; the CLI has none.
if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST'] = 'localhost';
}
if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
	$_SERVER['REQUEST_URI'] = '/';
}
define( 'WP_USE_THEMES', false );

require $rxcf_wp_load;
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
