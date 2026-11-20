<?php
/**
 * Admin notice shown when the database update fails.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Security\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Tells admins the plugin is paused and where the details are. Never shows the error itself.
 */
final class MigrationFailedNotice implements Hookable {

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: log source name. */
					__( 'Rachnix Checkout Field Editor could not update its database tables, so it has not loaded and your checkout is unchanged. Details are in WooCommerce > Status > Logs, source "%s". It will try again on the next page load.', 'rachnix-checkout-fields' ),
					Logger::SOURCE
				)
			)
		);
	}
}
