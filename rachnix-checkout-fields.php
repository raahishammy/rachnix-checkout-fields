<?php
/**
 * Plugin Name:          Rachnix Checkout Field Editor for WooCommerce
 * Description:          Edit, hide, reorder and add checkout fields on both the classic and the block checkout.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         8.2
 * Requires Plugins:     woocommerce
 * WC requires at least: 9.9
 * WC tested up to:      11.3
 * Author:               Rachnix
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          rachnix-checkout-fields
 * Domain Path:          /languages
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/*
 * This file must stay parseable on old PHP versions so it can show a notice
 * instead of a fatal error. Keep PHP 8 syntax out of it; everything else lives in src/.
 */
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: the PHP version the site runs. */
						__( 'Rachnix Checkout Field Editor needs PHP 8.2 or newer. This site runs PHP %s, so the plugin has not loaded and your checkout is unchanged.', 'rachnix-checkout-fields' ),
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

/*
 * PSR-4 autoloader for the plugin's own classes. The plugin has no runtime
 * Composer dependencies, so the release build ships without vendor/.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Rachnix\\CheckoutFields\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class_name, strlen( $prefix ) ) );
		$path     = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\Rachnix\CheckoutFields\Plugin::boot( __FILE__ );
	}
);
