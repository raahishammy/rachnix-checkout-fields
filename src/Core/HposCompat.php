<?php
/**
 * WooCommerce feature compatibility declarations.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Declares support for HPOS (custom order tables) and the Cart and Checkout blocks.
 */
final class HposCompat implements Hookable {

	/**
	 * WooCommerce feature ids this plugin supports.
	 */
	public const FEATURES = array( 'custom_order_tables', 'cart_checkout_blocks' );

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct(
		private readonly string $plugin_file,
	) {}

	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
	}

	/**
	 * Runs on `before_woocommerce_init`.
	 */
	public function declare_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}
		foreach ( self::FEATURES as $feature ) {
			FeaturesUtil::declare_compatibility( $feature, $this->plugin_file, true );
		}
	}
}
