<?php
/**
 * Platforms the plugin depends on.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

defined( 'ABSPATH' ) || exit;

// PHPCompatibility 9.x predates enums and wrongly flags $this inside enum methods.
// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext

/**
 * A runtime dependency and its minimum supported version.
 */
enum Dependency: string {

	case Php         = 'php';
	case WordPress   = 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit -- lowercase slug, not prose.
	case WooCommerce = 'woocommerce';

	/**
	 * Minimum supported version. Keep in sync with the plugin header.
	 */
	public function minimum(): string {
		return match ( $this ) {
			self::Php         => '8.2',
			self::WordPress   => '6.5',
			self::WooCommerce => '9.9',
		};
	}

	/**
	 * Product name. Proper nouns, so not translated.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Php         => 'PHP',
			self::WordPress   => 'WordPress',
			self::WooCommerce => 'WooCommerce',
		};
	}
}
