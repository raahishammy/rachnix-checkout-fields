<?php
/**
 * Checkout style settings.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The style mode, the four Custom controls, and the optional custom CSS.
 */
final readonly class StyleSettings {

	public const MAX_RADIUS = 32;

	public const MAX_SPACING = 64;

	public const MAX_CSS_BYTES = 10000;

	/**
	 * @param StyleMode     $mode           Style mode.
	 * @param string|null   $accent         Accent colour "#rrggbb"; null uses the theme's primary colour.
	 * @param int           $radius         Corner radius in px (Custom mode).
	 * @param int           $spacing        Space between fields in px (Custom mode).
	 * @param LabelPosition $label_position Label position (Custom mode).
	 * @param string        $custom_css     Extra CSS from a user allowed to edit CSS; never contains "<".
	 */
	public function __construct(
		public StyleMode $mode = StyleMode::Theme,
		public ?string $accent = null,
		public int $radius = 4,
		public int $spacing = 16,
		public LabelPosition $label_position = LabelPosition::Above,
		public string $custom_css = '',
	) {}

	/**
	 * Reads stored data. Anything malformed falls back to its default, so a damaged
	 * option never breaks the checkout.
	 *
	 * @param array<mixed> $data Stored settings.
	 */
	public static function from_array( array $data ): self {
		$defaults = new self();
		$accent   = $data['accent'] ?? null;
		$css      = $data['custom_css'] ?? '';

		return new self(
			is_string( $data['mode'] ?? null ) ? ( StyleMode::tryFrom( $data['mode'] ) ?? $defaults->mode ) : $defaults->mode,
			is_string( $accent ) && 1 === preg_match( '/^#[0-9a-f]{6}$/', $accent ) ? $accent : null,
			self::clamp( $data['radius'] ?? null, self::MAX_RADIUS, $defaults->radius ),
			self::clamp( $data['spacing'] ?? null, self::MAX_SPACING, $defaults->spacing ),
			is_string( $data['label_position'] ?? null ) ? ( LabelPosition::tryFrom( $data['label_position'] ) ?? $defaults->label_position ) : $defaults->label_position,
			is_string( $css ) && ! str_contains( $css, '<' ) && strlen( $css ) <= self::MAX_CSS_BYTES ? $css : '',
		);
	}

	/**
	 * @return array{mode: string, accent: string|null, radius: int, spacing: int, label_position: string, custom_css: string}
	 */
	public function to_array(): array {
		return array(
			'mode'           => $this->mode->value,
			'accent'         => $this->accent,
			'radius'         => $this->radius,
			'spacing'        => $this->spacing,
			'label_position' => $this->label_position->value,
			'custom_css'     => $this->custom_css,
		);
	}

	/**
	 * @param mixed $value    Stored value.
	 * @param int   $max      Upper limit.
	 * @param int   $fallback Default when not an integer.
	 */
	private static function clamp( mixed $value, int $max, int $fallback ): int {
		return is_int( $value ) ? max( 0, min( $max, $value ) ) : $fallback;
	}
}
