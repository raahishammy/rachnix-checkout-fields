<?php
/**
 * Smoke check run inside a real WordPress: `wp eval-file tests/smoke/activation.php`.
 * Exits non-zero if the plugin did not boot or did not declare WooCommerce compatibility.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$rxcf_failures = array();

if ( 1 !== did_action( 'rxcf_loaded' ) ) {
	$rxcf_failures[] = 'rxcf_loaded did not fire exactly once (requirements failed or boot did not run).';
}

$rxcf_plugin = 'rachnix-checkout-fields/rachnix-checkout-fields.php';
foreach ( array( 'custom_order_tables', 'cart_checkout_blocks' ) as $rxcf_feature ) {
	$rxcf_compat = FeaturesUtil::get_compatible_plugins_for_feature( $rxcf_feature, true );
	if ( ! in_array( $rxcf_plugin, $rxcf_compat['compatible'] ?? array(), true ) ) {
		$rxcf_failures[] = "Not declared compatible with {$rxcf_feature}.";
	}
}

if ( array() !== $rxcf_failures ) {
	fwrite( STDERR, implode( PHP_EOL, $rxcf_failures ) . PHP_EOL );
	exit( 1 );
}

echo 'Smoke check passed: plugin booted and declared HPOS + blocks compatibility.' . PHP_EOL;
