<?php
/**
 * Changes plugin settings.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Domain\AuditAction;
use Rachnix\CheckoutFields\Domain\LabelPosition;
use Rachnix\CheckoutFields\Domain\StyleMode;
use Rachnix\CheckoutFields\Domain\StyleSettings;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;
use Rachnix\CheckoutFields\Repository\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Validates style changes strictly (with a message per setting), saves them and audits them.
 */
final class SettingsService {

	/**
	 * @param SettingsRepository $settings Settings storage.
	 * @param AuditLogRepository $audit    Audit log.
	 */
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly AuditLogRepository $audit,
	) {}

	public function style(): StyleSettings {
		return $this->settings->style();
	}

	/**
	 * Partial update: only the given keys change.
	 *
	 * @param array<string, mixed> $changes      mode, accent, radius, spacing, label_position, custom_css.
	 * @param int                  $user_id      Acting user.
	 * @param bool                 $can_edit_css Whether the user may change custom CSS (WordPress `edit_css`).
	 * @throws InvalidFieldInput When a value is not acceptable.
	 */
	public function update_style( array $changes, int $user_id, bool $can_edit_css ): StyleSettings {
		$current = $this->settings->style();
		$updated = $this->prepare_style( $changes, $can_edit_css );
		$this->settings->save_style( $updated );
		$this->audit->record( AuditAction::Update, 'settings', 'style', $current->to_array(), $updated->to_array(), $user_id );
		return $updated;
	}

	/**
	 * Puts the style back to its defaults (Follow theme, no custom CSS).
	 *
	 * @param int $user_id Acting user.
	 */
	public function reset_style( int $user_id ): void {
		$current = $this->settings->style();
		$this->settings->save_style( new StyleSettings() );
		$this->audit->record( AuditAction::Reset, 'settings', 'style', $current->to_array(), ( new StyleSettings() )->to_array(), $user_id );
	}

	/**
	 * Validates style changes and returns the result, without saving (also used by import,
	 * so an invalid style is reported before any field changes are applied).
	 *
	 * @param array<string, mixed> $changes      Changes.
	 * @param bool                 $can_edit_css Whether the user may change custom CSS.
	 * @throws InvalidFieldInput When a value is not acceptable.
	 */
	public function prepare_style( array $changes, bool $can_edit_css ): StyleSettings {
		$current = $this->settings->style();
		$errors  = array();
		$data    = $current->to_array();

		if ( array_key_exists( 'mode', $changes ) ) {
			$mode = is_string( $changes['mode'] ) ? StyleMode::tryFrom( $changes['mode'] ) : null;
			if ( null === $mode ) {
				$errors['style.mode'] = __( 'Choose Follow theme, Plugin style or Custom.', 'rachnix-checkout-fields' );
			} else {
				$data['mode'] = $mode->value;
			}
		}

		if ( array_key_exists( 'accent', $changes ) ) {
			if ( null === $changes['accent'] || '' === $changes['accent'] ) {
				$data['accent'] = null;
			} else {
				$hex = is_string( $changes['accent'] ) ? sanitize_hex_color( $changes['accent'] ) : null;
				if ( ! is_string( $hex ) || '' === $hex ) {
					$errors['style.accent'] = __( 'Use a colour like #1a73e8.', 'rachnix-checkout-fields' );
				} else {
					$data['accent'] = self::long_hex( $hex );
				}
			}
		}

		foreach ( array(
			'radius'  => StyleSettings::MAX_RADIUS,
			'spacing' => StyleSettings::MAX_SPACING,
		) as $key => $max ) {
			if ( array_key_exists( $key, $changes ) ) {
				$value = $changes[ $key ];
				if ( ! is_int( $value ) || $value < 0 || $value > $max ) {
					/* translators: %d: largest allowed value in pixels. */
					$errors[ "style.{$key}" ] = sprintf( __( 'Use a whole number of pixels from 0 to %d.', 'rachnix-checkout-fields' ), $max );
				} else {
					$data[ $key ] = $value;
				}
			}
		}

		if ( array_key_exists( 'label_position', $changes ) ) {
			$position = is_string( $changes['label_position'] ) ? LabelPosition::tryFrom( $changes['label_position'] ) : null;
			if ( null === $position ) {
				$errors['style.label_position'] = __( 'Choose above or beside the field.', 'rachnix-checkout-fields' );
			} else {
				$data['label_position'] = $position->value;
			}
		}

		if ( array_key_exists( 'custom_css', $changes ) && $changes['custom_css'] !== $current->custom_css ) {
			$css = is_string( $changes['custom_css'] ) ? $changes['custom_css'] : '';
			if ( ! $can_edit_css ) {
				$errors['style.custom_css'] = __( 'You need permission to edit CSS to change this.', 'rachnix-checkout-fields' );
			} elseif ( strlen( $css ) > StyleSettings::MAX_CSS_BYTES ) {
				/* translators: %d: maximum size in bytes. */
				$errors['style.custom_css'] = sprintf( __( 'Custom CSS can be at most %d bytes.', 'rachnix-checkout-fields' ), StyleSettings::MAX_CSS_BYTES );
			} else {
				// "<" never belongs in a stylesheet; removing it means the CSS can never close the <style> tag.
				$data['custom_css'] = str_replace( '<', '', wp_strip_all_tags( $css ) );
			}
		}

		if ( array() !== $errors ) {
			throw InvalidFieldInput::with( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		return StyleSettings::from_array( $data );
	}

	/**
	 * Saves a style already checked by prepare_style() (import).
	 *
	 * @param StyleSettings $style   Style.
	 * @param int           $user_id Acting user.
	 * @param AuditAction   $action  How it is logged.
	 */
	public function save_prepared_style( StyleSettings $style, int $user_id, AuditAction $action ): void {
		$current = $this->settings->style();
		$this->settings->save_style( $style );
		$this->audit->record( $action, 'settings', 'style', $current->to_array(), $style->to_array(), $user_id );
	}

	public function wizard_completed(): bool {
		return $this->settings->wizard_completed();
	}

	public function complete_wizard(): void {
		$this->settings->complete_wizard();
	}

	/**
	 * "#abc" becomes "#aabbcc"; output is lowercase.
	 *
	 * @param string $hex Sanitised hex colour.
	 */
	private static function long_hex( string $hex ): string {
		$hex = strtolower( $hex );
		return 4 === strlen( $hex ) ? '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3] : $hex;
	}
}
