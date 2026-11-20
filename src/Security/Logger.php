<?php
/**
 * Error logging through WooCommerce.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to WooCommerce > Status > Logs under the plugin's source. Never prints to the screen.
 * Callers must not pass personal data.
 */
final class Logger {

	public const SOURCE = 'rachnix-checkout-fields';

	/**
	 * @param string                     $message What went wrong.
	 * @param array<string, scalar|null> $context Ids and error codes only.
	 */
	public function error( string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->error( $message, array( 'source' => self::SOURCE ) + $context );
	}
}
