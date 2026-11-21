<?php
/**
 * Loads the plugin's translations.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\I18n;

use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Translations from WordPress.org load automatically; this also loads any .mo files shipped
 * in /languages. On `init`, so translations are not loaded too early (WordPress 6.7+ warns).
 */
final class TextDomain implements Hookable {

	public const DOMAIN = 'rachnix-checkout-fields';

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct(
		private readonly string $plugin_file,
	) {}

	public function register(): void {
		add_action( 'init', array( $this, 'load' ) );
	}

	public function load(): void {
		load_plugin_textdomain( self::DOMAIN, false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
	}
}
