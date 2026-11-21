<?php
/**
 * Checkout stylesheet and style mode.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout;

use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\LabelPosition;
use Rachnix\CheckoutFields\Domain\StyleMode;
use Rachnix\CheckoutFields\Plugin;
use Rachnix\CheckoutFields\Repository\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Loads assets/css/checkout.css on the checkout page only (no JavaScript), marks the
 * body with the style mode, and adds the Custom mode variables and custom CSS inline.
 */
final class CheckoutStyles implements Hookable {

	public const HANDLE = 'rxcf-checkout';

	/**
	 * @param SettingsRepository $settings    Settings.
	 * @param string             $plugin_file Absolute path to the main plugin file.
	 * @param (\Closure(): bool)|null $on_checkout Overrides checkout-page detection (tests).
	 */
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly string $plugin_file,
		private readonly ?\Closure $on_checkout = null,
	) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	public function enqueue(): void {
		if ( ! $this->on_checkout() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, plugins_url( 'assets/css/checkout.css', $this->plugin_file ), array(), Plugin::VERSION );

		$inline = $this->inline_css();
		if ( '' !== $inline ) {
			wp_add_inline_style( self::HANDLE, $inline );
		}
	}

	/**
	 * @param mixed $classes Body classes.
	 * @return mixed
	 */
	public function body_class( mixed $classes ): mixed {
		if ( ! is_array( $classes ) || ! $this->on_checkout() ) {
			return $classes;
		}
		$style     = $this->settings->style();
		$classes[] = 'rxcf-style-' . $style->mode->value;
		// Parent theme, so child themes get the same theme fixes (see "Theme fixes" in checkout.css).
		$classes[] = 'rxcf-theme-' . sanitize_html_class( get_template() );
		if ( StyleMode::Custom === $style->mode && LabelPosition::Side === $style->label_position ) {
			$classes[] = 'rxcf-labels-side';
		}
		return $classes;
	}

	/**
	 * Custom mode variables, then the custom CSS (any mode). Every value is validated on
	 * save: integers, a "#rrggbb" colour, and CSS without "<".
	 */
	public function inline_css(): string {
		$style = $this->settings->style();
		$css   = '';

		if ( StyleMode::Custom === $style->mode ) {
			$css = sprintf( ':root{--rxcf-radius:%dpx;--rxcf-gap:%dpx', $style->radius, $style->spacing );
			if ( null !== $style->accent ) {
				$css .= ';--rxcf-accent:' . $style->accent;
			}
			$css .= '}';
		}

		if ( '' !== $style->custom_css ) {
			$css .= "\n" . str_replace( '<', '', $style->custom_css );
		}
		return $css;
	}

	private function on_checkout(): bool {
		if ( null !== $this->on_checkout ) {
			return ( $this->on_checkout )();
		}
		return function_exists( 'is_checkout' ) && is_checkout();
	}
}
