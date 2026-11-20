<?php
/**
 * Environment version checks.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Compares installed versions against the minimums. Holds no WordPress calls
 * except in `from_environment()`, so it can be unit tested with any versions.
 */
final readonly class Requirements {

	/**
	 * @param string      $php_version         Running PHP version.
	 * @param string      $wordpress_version   Running WordPress version.
	 * @param string|null $woocommerce_version Active WooCommerce version, or null when inactive.
	 */
	public function __construct(
		private string $php_version,
		private string $wordpress_version,
		private ?string $woocommerce_version,
	) {}

	/**
	 * Reads the versions of the current request. Call on or after `plugins_loaded`.
	 */
	public static function from_environment(): self {
		$wordpress_version = $GLOBALS['wp_version'] ?? '0';

		return new self(
			PHP_VERSION,
			is_string( $wordpress_version ) ? $wordpress_version : '0',
			defined( 'WC_VERSION' ) ? WC_VERSION : null,
		);
	}

	/**
	 * Requirements that are not met; empty when the plugin can load.
	 *
	 * @return list<UnmetRequirement>
	 */
	public function unmet(): array {
		$installed = array(
			Dependency::Php->value         => $this->php_version,
			Dependency::WordPress->value   => $this->wordpress_version,
			Dependency::WooCommerce->value => $this->woocommerce_version,
		);

		$unmet = array();
		foreach ( Dependency::cases() as $dependency ) {
			$found = $installed[ $dependency->value ];
			if ( null === $found || version_compare( $found, $dependency->minimum(), '<' ) ) {
				$unmet[] = new UnmetRequirement( $dependency, $found );
			}
		}

		return $unmet;
	}
}
