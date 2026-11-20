<?php
/**
 * Composition root.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields;

use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Core\HposCompat;
use Rachnix\CheckoutFields\Core\MigrationFailedNotice;
use Rachnix\CheckoutFields\Core\Migrations\CreateTables;
use Rachnix\CheckoutFields\Core\Migrator;
use Rachnix\CheckoutFields\Core\Requirements;
use Rachnix\CheckoutFields\Core\RequirementsNotice;
use Rachnix\CheckoutFields\Core\TableNames;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\FieldCache;
use Rachnix\CheckoutFields\Repository\FieldRepository;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\Sanitizer;
use Rachnix\CheckoutFields\Service\ValidationService;

defined( 'ABSPATH' ) || exit;

/**
 * Wires services together and registers their hooks. `boot()` is the only static entry point.
 */
final class Plugin {

	public const VERSION = '0.1.0';

	/** Option holding each site's installed schema version. */
	public const DB_VERSION_OPTION = 'rxcf_db_version';

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
		$c = $this->container;

		$c->set( HposCompat::class, fn() => new HposCompat( $this->plugin_file ) );
		$c->set( Logger::class, static fn() => new Logger() );
		$c->set( TableNames::class, static fn() => new TableNames( self::wpdb()->prefix . 'rxcf_' ) );

		$c->set(
			Migrator::class,
			static fn( Container $c ) => new Migrator(
				self::wpdb(),
				$c->get( TableNames::class ),
				array( new CreateTables() ),
				self::DB_VERSION_OPTION
			)
		);

		$c->set(
			FieldTypeRegistry::class,
			static function (): FieldTypeRegistry {
				$registry = new FieldTypeRegistry();
				$registry->register_free_types();

				/**
				 * Register extra field types. Fires once, the first time the registry is needed.
				 *
				 * @param FieldTypeRegistry $registry Call `$registry->register( new MyType() )`.
				 */
				do_action( 'rxcf_register_field_types', $registry );

				return $registry;
			}
		);

		$c->set( PatternLibrary::class, static fn() => new PatternLibrary() );
		$c->set( Sanitizer::class, static fn( Container $c ) => new Sanitizer( $c->get( FieldTypeRegistry::class ) ) );
		$c->set(
			ValidationService::class,
			static fn( Container $c ) => new ValidationService(
				$c->get( FieldTypeRegistry::class ),
				$c->get( PatternLibrary::class ),
				$c->get( Logger::class )
			)
		);

		$c->set(
			FieldRepositoryInterface::class,
			static fn( Container $c ) => new FieldRepository(
				self::wpdb(),
				$c->get( TableNames::class ),
				new FieldCache( 'rxcf_field_rows_v1' ),
				$c->get( Logger::class )
			)
		);
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
	 * Declares WooCommerce compatibility, then loads features only when requirements are met
	 * and the database schema is current.
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

		if ( ! $this->schema_is_current() ) {
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

	/**
	 * Runs pending migrations. On failure, logs the cause and shows an admin notice.
	 */
	private function schema_is_current(): bool {
		try {
			// False means another request is migrating; features load on the next request.
			return $this->container->get( Migrator::class )->migrate();
		} catch ( DatabaseError $e ) {
			$this->container->get( Logger::class )->error( 'Database migration failed.', array( 'reason' => $e->getMessage() ) );
			( new MigrationFailedNotice() )->register();
			return false;
		}
	}

	private static function wpdb(): \wpdb {
		global $wpdb;
		return $wpdb;
	}
}
