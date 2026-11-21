<?php
/**
 * Sets the style mode in wp-env: `wp eval-file tests/e2e/style.php theme|plugin|custom`.
 * Result line: `RXCF_E2E {json}`. wp-env only.
 *
 * No declare(strict_types=1): `wp eval-file` runs this file through eval().
 *
 * @package Rachnix\CheckoutFields
 */

defined( 'ABSPATH' ) || exit;

$rxcf_mode   = (string) ( $args[0] ?? 'theme' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- $args is set by wp eval-file.
$rxcf_styles = array(
	'theme'  => array( 'mode' => 'theme' ),
	'plugin' => array( 'mode' => 'plugin' ),
	// A deliberately visible Custom style, so its screenshots show the controls at work.
	'custom' => array(
		'mode'           => 'custom',
		'accent'         => '#d63638',
		'radius'         => 12,
		'spacing'        => 24,
		'label_position' => 'side',
	),
);
if ( ! isset( $rxcf_styles[ $rxcf_mode ] ) ) {
	fwrite( STDERR, "Unknown style mode {$rxcf_mode}." . PHP_EOL );
	exit( 1 );
}

$rxcf_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) ( $rxcf_admins[0] ?? 0 ) );

$rxcf_request = new WP_REST_Request( 'POST', '/rxcf/v1/settings' );
$rxcf_request->set_header( 'Content-Type', 'application/json' );
$rxcf_request->set_body( (string) wp_json_encode( array( 'style' => $rxcf_styles[ $rxcf_mode ] ) ) );
$rxcf_response = rest_do_request( $rxcf_request );

if ( 200 !== $rxcf_response->get_status() ) {
	fwrite( STDERR, 'Could not set the style: ' . wp_json_encode( $rxcf_response->get_data() ) . PHP_EOL );
	exit( 1 );
}

echo 'RXCF_E2E ' . wp_json_encode( array( 'mode' => $rxcf_mode ) ) . PHP_EOL;
