<?php
/**
 * Smoke check run inside a real WordPress: `wp eval-file tests/smoke/activation.php`.
 * Exits non-zero if the plugin did not boot or did not declare WooCommerce compatibility.
 *
 * No declare(strict_types=1) here, unlike every other file: `wp eval-file` runs the
 * file through eval(), where a declare statement is a fatal error.
 *
 * @package Rachnix\CheckoutFields
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$rxcf_failures = array();

if ( ! defined( 'WC_VERSION' ) ) {
	fwrite( STDERR, 'WooCommerce is not active, so nothing else can be checked.' . PHP_EOL );
	exit( 1 );
}

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

if ( (int) get_option( 'rxcf_db_version', 0 ) < 1 ) {
	$rxcf_failures[] = 'Database schema was not migrated (rxcf_db_version < 1).';
}

global $wpdb;
foreach ( array( 'fields', 'sections', 'audit_log' ) as $rxcf_table ) {
	$rxcf_name = $wpdb->prefix . 'rxcf_' . $rxcf_table;
	if ( $rxcf_name !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $rxcf_name ) ) ) ) {
		$rxcf_failures[] = "Table {$rxcf_name} is missing.";
	}
}

if ( array() !== $rxcf_failures ) {
	fwrite( STDERR, implode( PHP_EOL, $rxcf_failures ) . PHP_EOL );
	exit( 1 );
}

echo 'Smoke check passed: plugin booted, migrated its tables and declared HPOS + blocks compatibility.' . PHP_EOL;
