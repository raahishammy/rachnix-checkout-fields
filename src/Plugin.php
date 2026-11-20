<?php
/**
 * Composition root.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields;

use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Core\HposCompat;
use Rachnix\CheckoutFields\Core\Requirements;
use Rachnix\CheckoutFields\Core\RequirementsNotice;

defined( 'ABSPATH' ) || exit;

/**
 * Wires services together and registers their hooks. `boot()` is the only static entry point.
 */
final class Plugin {

	public const VERSION = '0.1.0';

	private static bool $booted = false;

	/**
	 * @param Container $container   Service container.
	 * @param string    $plugin_file Absolute path to the main plugin file.
	 */
	private function __construct(
		private readonly Container $container,
		private readonly string $plugin_file,
	) {}

	/**
	 * Starts the plugin. Safe to call more than once; later calls do nothing.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public static function boot( string $plugin_file ): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		$plugin = new self( new Container(), $plugin_file );
		$plugin->register_services();
		$plugin->start( Requirements::from_environment() );
	}

	/**
	 * Registers service factories. Nothing is constructed until it is first needed.
	 */
	private function register_services(): void {
		$this->container->set( HposCompat::class, fn() => new HposCompat( $this->plugin_file ) );
	}

	/**
	 * Integrations whose hooks are added once all requirements are met, in order.
	 * Register each factory in `register_services()`, then list it here.
	 *
	 * @return list<Hookable>
	 */
	private function hookables(): array {
		return array();
	}

	/**
	 * Declares WooCommerce compatibility, then loads features only when requirements are met.
	 *
	 * @param Requirements $requirements Environment check.
	 */
	private function start( Requirements $requirements ): void {
		// Declared even when requirements fail, so WooCommerce never flags the plugin as incompatible.
		$this->container->get( HposCompat::class )->register();

		$unmet = $requirements->unmet();
		if ( array() !== $unmet ) {
			( new RequirementsNotice( $unmet ) )->register();
			return;
		}

		foreach ( $this->hookables() as $hookable ) {
			$hookable->register();
		}

		/**
		 * Fires once the free plugin has loaded and all requirements are met.
		 * The Pro add-on waits for this action before it loads.
		 *
		 * @param string $version Free plugin version.
		 */
		do_action( 'rxcf_loaded', self::VERSION );
	}
}
