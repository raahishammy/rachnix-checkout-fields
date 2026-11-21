<?php
/**
 * Which checkout the store uses.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Asks WooCommerce whether the checkout uses the Checkout block (on block themes this
 * reads the checkout template, which costs a query), and caches the answer. The cache
 * is cleared whenever a page or template is saved, the checkout page changes or the
 * theme switches.
 */
final class CheckoutDetector implements Hookable {

	private const CACHE_KEY = 'rxcf_uses_block_checkout';

	private ?bool $uses_blocks = null;

	public function register(): void {
		add_action( 'save_post', array( $this, 'clear' ) );
		add_action( 'deleted_post', array( $this, 'clear' ) );
		add_action( 'switch_theme', array( $this, 'clear' ) );
		add_action( 'update_option_woocommerce_checkout_page_id', array( $this, 'clear' ) );
	}

	public function uses_block_checkout(): bool {
		if ( null !== $this->uses_blocks ) {
			return $this->uses_blocks;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( 'yes' === $cached || 'no' === $cached ) {
			$this->uses_blocks = 'yes' === $cached;
			return $this->uses_blocks;
		}

		$this->uses_blocks = $this->detect();
		set_transient( self::CACHE_KEY, $this->uses_blocks ? 'yes' : 'no', DAY_IN_SECONDS );
		return $this->uses_blocks;
	}

	public function clear(): void {
		$this->uses_blocks = null;
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Available in every supported WooCommerce version (9.9+).
	 */
	private function detect(): bool {
		return (bool) CartCheckoutUtils::is_checkout_block_default();
	}
}
