<?php
/**
 * Makes the wp-env store look like a real shop for the WordPress.org screenshots:
 * `wp eval-file tests/e2e/listing-setup.php [theme]`. Run after setup.php.
 * Renames the store and the test product, and activates the theme if it is installed.
 * Changes store options: wp-env only, never a real store.
 *
 * No declare(strict_types=1): `wp eval-file` runs this file through eval().
 *
 * @package Rachnix\CheckoutFields
 */

defined( 'ABSPATH' ) || exit;

update_option( 'blogname', 'Linen & Loom' );
update_option( 'blogdescription', 'Handmade home textiles' );

$rxcf_listing_products = wc_get_products(
	array(
		'sku'   => 'rxcf-e2e',
		'limit' => 1,
	)
);
foreach ( $rxcf_listing_products as $rxcf_listing_product ) {
	$rxcf_listing_product->set_name( 'Linen tote bag' );
	$rxcf_listing_product->set_regular_price( '24' );
	$rxcf_listing_product->save();
}

$rxcf_listing_theme = sanitize_key( $args[0] ?? '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- $args is set by wp eval-file.
if ( '' !== $rxcf_listing_theme && wp_get_theme( $rxcf_listing_theme )->exists() ) {
	switch_theme( $rxcf_listing_theme );
}

echo 'RXCF_E2E ' . wp_json_encode( array( 'theme' => get_stylesheet() ) ) . PHP_EOL;
