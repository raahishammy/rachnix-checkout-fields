<?php
/**
 * The admin screen.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Admin;

use Rachnix\CheckoutFields\Checkout\Blocks\BlockFieldSupport;
use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Plugin;
use Rachnix\CheckoutFields\Repository\SettingsRepository;
use Rachnix\CheckoutFields\Rest\AbstractController;
use Rachnix\CheckoutFields\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce > Checkout Fields. Renders an empty root element that the React app
 * (assets/build/index.js, built from assets/src) takes over. Scripts load on this screen only.
 */
final class AdminPage implements Hookable {

	public const SLUG = 'rxcf-checkout-fields';

	public const SCRIPT_HANDLE = 'rxcf-admin';

	private string $hook_suffix = '';

	/**
	 * @param string            $plugin_file Absolute path to the main plugin file.
	 * @param CheckoutDetector  $detector    Which checkout is in use.
	 * @param BlockFieldSupport       $support     Block checkout type support.
	 * @param SettingsRepository|null $settings    Settings (setup wizard state).
	 */
	public function __construct(
		private readonly string $plugin_file,
		private readonly CheckoutDetector $detector,
		private readonly BlockFieldSupport $support,
		private readonly ?SettingsRepository $settings = null,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu(): void {
		$suffix            = add_submenu_page(
			'woocommerce',
			__( 'Checkout Fields', 'rachnix-checkout-fields' ),
			__( 'Checkout Fields', 'rachnix-checkout-fields' ),
			Capabilities::MANAGE_FIELDS,
			self::SLUG,
			array( $this, 'render' )
		);
		$this->hook_suffix = is_string( $suffix ) ? $suffix : '';
	}

	public function render(): void {
		echo '<div class="wrap rxcf-admin-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Checkout Fields', 'rachnix-checkout-fields' ) . '</h1>';
		echo '<div id="rxcf-admin"><p>' . esc_html__( 'Loading the checkout field editor…', 'rachnix-checkout-fields' ) . '</p></div>';
		echo '<noscript><p>' . esc_html__( 'The checkout field editor needs JavaScript.', 'rachnix-checkout-fields' ) . '</p></noscript></div>';
	}

	/**
	 * @param mixed $hook_suffix Current admin screen.
	 */
	public function enqueue( mixed $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$dir   = dirname( $this->plugin_file );
		$asset = $dir . '/assets/build/index.asset.php';
		if ( ! is_readable( $asset ) ) {
			add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );
			return;
		}

		$meta    = require $asset;
		$deps    = is_array( $meta['dependencies'] ?? null ) ? $meta['dependencies'] : array();
		$version = is_string( $meta['version'] ?? null ) ? $meta['version'] : Plugin::VERSION;

		wp_enqueue_script( self::SCRIPT_HANDLE, plugins_url( 'assets/build/index.js', $this->plugin_file ), $deps, $version, true );
		wp_set_script_translations( self::SCRIPT_HANDLE, 'rachnix-checkout-fields', $dir . '/languages' );
		wp_add_inline_script( self::SCRIPT_HANDLE, 'window.rxcfAdmin = ' . wp_json_encode( $this->config() ) . ';', 'before' );

		wp_enqueue_style( 'wp-components' );
		// @wordpress/scripts names the CSS of an imported "style.scss" style-index.css (other
		// names give index.css), plus an -rtl.css twin that WordPress swaps in for RTL languages.
		foreach ( array( 'style-index', 'index' ) as $name ) {
			if ( is_readable( "{$dir}/assets/build/{$name}.css" ) ) {
				wp_enqueue_style( self::SCRIPT_HANDLE, plugins_url( "assets/build/{$name}.css", $this->plugin_file ), array( 'wp-components' ), $version );
				if ( is_readable( "{$dir}/assets/build/{$name}-rtl.css" ) ) {
					wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' );
				}
				break;
			}
		}
	}

	/**
	 * Data the app needs before its first request. The REST nonce comes from wp-api-fetch.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		return array(
			'namespace'             => AbstractController::NAMESPACE,
			'version'               => Plugin::VERSION,
			'checkoutUrl'           => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'usesBlockCheckout'     => $this->detector->uses_block_checkout(),
			'blockDateFields'       => $this->support->date_supported(),
			'canEditCss'            => current_user_can( 'edit_css' ),
			'wizardCompleted'       => $this->settings?->wizard_completed() ?? true,
			'restoreFieldsUrl'      => admin_url( 'admin.php?page=' . self::SLUG ),
			'deleteDataOnUninstall' => $this->settings?->delete_data_on_uninstall() ?? false,
			'canDeletePlugins'      => current_user_can( 'delete_plugins' ),
		);
	}

	public function missing_build_notice(): void {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'The checkout field editor has not been built. Run "npm install" and "npm run build" in the plugin folder.', 'rachnix-checkout-fields' ) . '</p></div>';
	}
}
