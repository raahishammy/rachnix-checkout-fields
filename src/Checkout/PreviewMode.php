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
 * renders even with an empty cart, the admin bar is hidden, and a banner says orders cannot
 * be placed. (The admin screen shows the frame as inert, so nothing can be submitted from it.)
 */
final class PreviewMode implements Hookable {

	private bool $active = false;

	private bool $banner_printed = false;

	/**
	 * @param PreviewService  $previews   Draft previews.
	 * @param FieldRepository $repository Field storage.
	 */
	public function __construct(
		private readonly PreviewService $previews,
		private readonly FieldRepository $repository,
	) {}

	/**
	 * On woocommerce_init, priority 1: WooCommerce has created WC()->countries by then (the
	 * draft's edits to WooCommerce's own fields are validated against it), and it is still
	 * before the block checkout integration registers fields (priority 10).
	 */
	public const PRIORITY = 1;

	public function register(): void {
		add_action( 'woocommerce_init', array( $this, 'maybe_activate' ), self::PRIORITY );
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
		// A banner at the top of the page, not a replaced order button: WooCommerce reloads the
		// payment area in a background request without the preview link, which would undo it.
		add_action( 'wp_body_open', array( $this, 'print_banner' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'print_banner' ), 1 );
		nocache_headers();
	}

	/**
	 * Prints the preview banner once (wp_body_open where the theme supports it, otherwise above
	 * the classic checkout form).
	 */
	public function print_banner(): void {
		if ( $this->banner_printed ) {
			return;
		}
		$this->banner_printed = true;
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- banner() escapes its text.
	}

	public function banner(): string {
		return '<div class="rxcf-preview-note" role="status" style="margin:0;padding:.75em 1em;background:#fcf9e8;border-bottom:1px solid #dba617;font:600 14px/1.4 sans-serif;color:#1d2327">'
			. esc_html__( 'Preview of your unpublished changes. Orders cannot be placed here.', 'rachnix-checkout-fields' )
			. '</div>';
	}
}
