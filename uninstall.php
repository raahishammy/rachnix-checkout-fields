<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the plugin's own data only if the store owner turned on "Delete all data when
 * the plugin is deleted" (Checkout Fields > Tools). Field values saved on orders are kept.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Like the main file, this one must parse on old PHP; the code it loads needs 8.2.
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	return;
}

require_once __DIR__ . '/src/Core/TableNames.php';
require_once __DIR__ . '/src/Core/Uninstaller.php';

( new \Rachnix\CheckoutFields\Core\Uninstaller() )->run();
