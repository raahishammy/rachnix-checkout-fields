<?php
/**
 * Prepares the wp-env store for the end-to-end tests:
 * `wp eval-file tests/e2e/setup.php [classic|blocks]` (the checkout page to use).
 * Safe to run repeatedly. Prints one result line: `RXCF_E2E {json}`.
 * Resets every section and changes store options: wp-env only, never a real store.
 *
 * No declare(strict_types=1): `wp eval-file` runs this file through eval().
 *
 * @package Rachnix\CheckoutFields
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param string               $message Failure.
 * @param array<string, mixed> $context Details.
 */
function rxcf_e2e_fail( string $message, array $context = array() ): void {
	fwrite( STDERR, $message . ' ' . wp_json_encode( $context ) . PHP_EOL );
	exit( 1 );
}

/**
 * Calls the plugin's REST API in-process as the current user.
 *
 * @param string               $method Method.
 * @param string               $path   Path below /rxcf/v1.
 * @param array<string, mixed> $body   JSON body.
 */
function rxcf_e2e_api( string $method, string $path, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, '/rxcf/v1' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( (string) wp_json_encode( $body ) );
	return rest_do_request( $request );
}

// Store basics: open to visitors, US base address, company field shown, cash on delivery.
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_default_country', 'US:CA' );
update_option( 'woocommerce_checkout_company_field', 'optional' );
update_option(
	'woocommerce_cod_settings',
	array(
		'enabled'            => 'yes',
		'title'              => 'Cash on delivery',
		'enable_for_virtual' => 'yes',
	)
);

// Which checkout to test: `wp eval-file setup.php classic` (default) or `... blocks`.
$rxcf_mode = 'blocks' === ( $args[0] ?? 'classic' ) ? 'blocks' : 'classic'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- $args is set by wp eval-file.

if ( 'blocks' === $rxcf_mode ) {
	// WooCommerce's own Checkout block page content (the same markup it installs).
	$rxcf_content = '<!-- wp:woocommerce/checkout /-->';
	if ( method_exists( 'WC_Install', 'get_checkout_block_content' ) ) {
		$rxcf_content = ( new ReflectionMethod( 'WC_Install', 'get_checkout_block_content' ) )->invoke( null );
	}
	$rxcf_slug  = 'block-checkout';
	$rxcf_title = 'Block checkout';
} else {
	$rxcf_content = '[woocommerce_checkout]';
	$rxcf_slug    = 'classic-checkout';
	$rxcf_title   = 'Classic checkout';
}

$rxcf_page    = get_page_by_path( $rxcf_slug );
$rxcf_page_id = $rxcf_page instanceof WP_Post ? $rxcf_page->ID : wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $rxcf_title,
		'post_name'    => $rxcf_slug,
		'post_content' => $rxcf_content,
	)
);
update_option( 'woocommerce_checkout_page_id', $rxcf_page_id );
delete_transient( 'rxcf_uses_block_checkout' ); // The plugin re-detects the checkout on the next request.

// The setup wizard is done, so tests open the editor; `setup.php classic wizard` leaves it pending.
$rxcf_settings                     = get_option( 'rxcf_settings', array() );
$rxcf_settings                     = is_array( $rxcf_settings ) ? $rxcf_settings : array();
$rxcf_settings['wizard_completed'] = 'wizard' !== ( $args[1] ?? '' );
update_option( 'rxcf_settings', $rxcf_settings, true );

// A virtual product, so no shipping method is needed.
$rxcf_products = wc_get_products(
	array(
		'sku'    => 'rxcf-e2e',
		'limit'  => 1,
		'return' => 'ids',
	)
);
if ( array() === $rxcf_products ) {
	$rxcf_product = new WC_Product_Simple();
	$rxcf_product->set_name( 'E2E product' );
	$rxcf_product->set_sku( 'rxcf-e2e' );
	$rxcf_product->set_regular_price( '10' );
	$rxcf_product->set_virtual( true );
	$rxcf_product->set_status( 'publish' );
	$rxcf_products = array( $rxcf_product->save() );
}

// Fields go through the plugin's own REST API, as the admin screen will.
$rxcf_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) ( $rxcf_admins[0] ?? 0 ) );

foreach ( array( 'billing', 'shipping', 'order', 'contact' ) as $rxcf_section ) {
	rxcf_e2e_api( 'POST', "/sections/{$rxcf_section}/reset", array( 'confirm' => true ) );
}

$rxcf_choices = static fn( string ...$values ): array => array_map(
	static fn( string $v ): array => array(
		'value' => $v,
		'label' => ucfirst( $v ),
	),
	$values
);

// One field of every free type.
$rxcf_fields = array(
	array(
		'billing',
		'rx_vat',
		'text',
		'VAT number',
		array(
			'required' => true,
			// Shown as a column in the orders list (checked by expectValuesForStore()).
			'display'  => array( 'order_list' => true ),
		),
	),
	array( 'billing', 'rx_alt_email', 'email', 'Other email', array() ),
	array( 'billing', 'rx_mobile', 'phone', 'Second phone', array() ),
	array( 'billing', 'rx_source', 'hidden', 'Source', array( 'default' => 'e2e' ) ),
	array( 'order', 'rx_note', 'textarea', 'Delivery note', array() ),
	array(
		'order',
		'rx_floor',
		'number',
		'Floor',
		array(
			'required'   => true,
			'validation' => array(
				array(
					'rule' => 'number_range',
					'min'  => 0,
					'max'  => 50,
				),
			),
		),
	),
	array( 'order', 'rx_pin', 'password', 'Gift card PIN', array() ),
	array(
		'order',
		'rx_slot',
		'select',
		'Delivery slot',
		array(
			'required' => true,
			'options'  => $rxcf_choices( 'am', 'pm' ),
		),
	),
	array( 'order', 'rx_wrap', 'radio', 'Wrapping', array( 'options' => $rxcf_choices( 'paper', 'box' ) ) ),
	array( 'order', 'rx_gift', 'checkbox', 'This is a gift', array() ),
	array( 'order', 'rx_date', 'date', 'Delivery date', array() ),
);

foreach ( $rxcf_fields as $rxcf_priority => [ $rxcf_section, $rxcf_key, $rxcf_type, $rxcf_label, $rxcf_settings ] ) {
	$rxcf_response = rxcf_e2e_api(
		'POST',
		'/fields',
		array(
			'section'  => $rxcf_section,
			'key'      => $rxcf_key,
			'type'     => $rxcf_type,
			'label'    => $rxcf_label,
			'priority' => 200 + $rxcf_priority,
			'settings' => (object) $rxcf_settings,
		)
	);
	if ( 201 !== $rxcf_response->get_status() ) {
		rxcf_e2e_fail( "Could not create {$rxcf_key}.", (array) $rxcf_response->get_data() );
	}
}

// Core field edits: hide the company field, relabel the first name.
foreach ( array(
	'billing_company'    => array( 'enabled' => false ),
	'billing_first_name' => array( 'label' => 'Given name' ),
) as $rxcf_key => $rxcf_change ) {
	$rxcf_response = rxcf_e2e_api( 'POST', "/core-fields/billing/{$rxcf_key}", $rxcf_change );
	if ( 200 !== $rxcf_response->get_status() ) {
		rxcf_e2e_fail( "Could not customise {$rxcf_key}.", (array) $rxcf_response->get_data() );
	}
}

echo 'RXCF_E2E ' . wp_json_encode(
	array(
		'product_id'     => (int) $rxcf_products[0],
		'home_url'       => home_url( '/' ),
		'checkout_url'   => get_permalink( $rxcf_page_id ),
		'mode'           => $rxcf_mode,
		// The block checkout supports date fields from WooCommerce 11.2.
		'date_supported' => class_exists( 'Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldTypes\DateFieldType' ),
	)
) . PHP_EOL;
