<?php
/**
 * Admin notice shown when the plugin cannot load.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Explains in plain words which requirement is missing. Never blocks the site.
 */
final class RequirementsNotice implements Hookable {

	/**
	 * @param list<UnmetRequirement> $unmet Failed requirements.
	 */
	public function __construct(
		private readonly array $unmet,
	) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'network_admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Prints the notice for users who can fix the problem.
	 */
	public function render(): void {
		if ( array() === $this->unmet || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'Rachnix Checkout Field Editor has not loaded, and your checkout is unchanged.', 'rachnix-checkout-fields' );
		echo '</strong></p><ul style="list-style:disc;padding-left:2em">';
		foreach ( $this->unmet as $requirement ) {
			echo '<li>' . esc_html( $this->message( $requirement ) ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * One plain sentence per requirement. Translated here, at render time, not earlier.
	 *
	 * @param UnmetRequirement $requirement Failed requirement.
	 */
	private function message( UnmetRequirement $requirement ): string {
		$dependency = $requirement->dependency;

		if ( $requirement->is_missing() ) {
			return sprintf(
				/* translators: 1: product name, e.g. WooCommerce. 2: minimum version. */
				__( 'Install and activate %1$s %2$s or newer.', 'rachnix-checkout-fields' ),
				$dependency->label(),
				$dependency->minimum()
			);
		}

		return sprintf(
			/* translators: 1: product name, e.g. WooCommerce. 2: minimum version. 3: installed version. */
			__( 'Update %1$s to version %2$s or newer. This site runs version %3$s.', 'rachnix-checkout-fields' ),
			$dependency->label(),
			$dependency->minimum(),
			(string) $requirement->found
		);
	}
}
