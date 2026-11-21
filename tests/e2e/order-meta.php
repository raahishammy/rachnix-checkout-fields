<?php
/**
 * Prints an order's custom field meta (both checkouts): `wp eval-file tests/e2e/order-meta.php <order_id>`.
 * Result line: `RXCF_E2E {json}`.
 *
 * No declare(strict_types=1): `wp eval-file` runs this file through eval().
 *
 * @package Rachnix\CheckoutFields
 */

defined( 'ABSPATH' ) || exit;

$rxcf_order = wc_get_order( (int) ( $args[0] ?? 0 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- $args is set by wp eval-file.
if ( ! $rxcf_order instanceof WC_Order ) {
	fwrite( STDERR, 'Order not found.' . PHP_EOL );
	exit( 1 );
}

$rxcf_meta = array();
foreach ( $rxcf_order->get_meta_data() as $rxcf_item ) {
	$rxcf_data = $rxcf_item->get_data();
	// Classic checkout: _rxcf_{key}. Block checkout (WooCommerce storage): _wc_{group}/rachnix/{key}.
	if ( str_starts_with( (string) $rxcf_data['key'], '_rxcf_' ) || 1 === preg_match( '#^_wc_(billing|shipping|other)/rachnix/#', (string) $rxcf_data['key'] ) ) {
		$rxcf_meta[ $rxcf_data['key'] ] = $rxcf_data['value'];
	}
}
ksort( $rxcf_meta );

echo 'RXCF_E2E ' . wp_json_encode( $rxcf_meta ) . PHP_EOL;
