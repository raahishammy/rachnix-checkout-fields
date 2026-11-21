<?php
/**
 * Shows the checkout with the admin's unpublished changes.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout;

use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Security\Capabilities;
use Rachnix\CheckoutFields\Service\PreviewService;

defined( 'ABSPATH' ) || exit;

/**
 * On a front-end request with ?rxcf_preview=1, a valid nonce and the capability, the field
 * repository serves the user's draft for that request only, so both checkouts render it.
 * Customers never see drafts: no nonce, no capability, no preview. In preview the checkout
 * renders even with an empty cart, the admin bar is hidden, and the order button is replaced.
 * (The admin screen also shows the frame as inert, so nothing can be submitted from it.)
 */
final class PreviewMode implements Hookable {

	private bool $active = false;

	/**
	 * @param PreviewService  $previews   Draft previews.
	 * @param FieldRepository $repository Field storage.
	 */
	public function __construct(
		private readonly PreviewService $previews,
		private readonly FieldRepository $repository,
	) {}

	public function register(): void {
		// Before WooCommerce's init (priority 0), which registers block checkout fields.
		add_action( 'init', array( $this, 'maybe_activate' ), -100 );
	}

	public function is_active(): bool {
		return $this->active;
	}

	public function maybe_activate(): void {
		if ( is_admin() || wp_doing_ajax() || ! isset( $_GET[ PreviewService::QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below.
			return;
		}
		$nonce = isset( $_GET[ PreviewService::NONCE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ PreviewService::NONCE_ARG ] ) ) : '';
		if ( false === wp_verify_nonce( $nonce, PreviewService::NONCE_ACTION ) || ! current_user_can( Capabilities::MANAGE_FIELDS ) ) {
			return;
		}

		$fields = $this->previews->fields_for( get_current_user_id() );
		if ( null === $fields ) {
			return;
		}

		$this->repository->show_preview( $fields );
		$this->active = true;

		add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'woocommerce_order_button_html', array( $this, 'order_button' ) );
		nocache_headers();
	}

	public function order_button(): string {
		return '<p class="rxcf-preview-note"><strong>' . esc_html__( 'Preview of your unpublished changes. Orders cannot be placed here.', 'rachnix-checkout-fields' ) . '</strong></p>';
	}
}
