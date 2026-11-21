<?php
/**
 * Plugin settings in the `rxcf_settings` option.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Repository;

use Rachnix\CheckoutFields\Domain\StyleSettings;

defined( 'ABSPATH' ) || exit;

/**
 * One small, autoloaded option holding all settings, grouped by key ("style", later others).
 */
final class SettingsRepository {

	public const OPTION = 'rxcf_settings';

	private ?StyleSettings $style = null;

	/**
	 * @param string $option Option name; tests use their own.
	 */
	public function __construct(
		private readonly string $option = self::OPTION,
	) {}

	public function style(): StyleSettings {
		if ( null === $this->style ) {
			$all         = $this->all();
			$this->style = StyleSettings::from_array( is_array( $all['style'] ?? null ) ? $all['style'] : array() );
		}
		return $this->style;
	}

	/**
	 * @param StyleSettings $style New style settings.
	 */
	public function save_style( StyleSettings $style ): void {
		$all          = $this->all();
		$all['style'] = $style->to_array();
		update_option( $this->option, $all, true );
		$this->style = $style;
	}

	/**
	 * @return array<mixed>
	 */
	private function all(): array {
		$value = get_option( $this->option, array() );
		return is_array( $value ) ? $value : array();
	}
}
