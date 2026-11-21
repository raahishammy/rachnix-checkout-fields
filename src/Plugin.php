<?php
/**
 * Composition root.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields;

use Rachnix\CheckoutFields\Admin\AdminPage;
use Rachnix\CheckoutFields\I18n\StringTranslations;
use Rachnix\CheckoutFields\I18n\TextDomain;
use Rachnix\CheckoutFields\Privacy\PrivacyIntegration;
use Rachnix\CheckoutFields\Rest\ToolsController;
use Rachnix\CheckoutFields\Service\ImportExportService;
use Rachnix\CheckoutFields\Display\BlockFieldVisibility;
use Rachnix\CheckoutFields\Display\CustomerDisplay;
use Rachnix\CheckoutFields\Display\EmailDisplay;
use Rachnix\CheckoutFields\Display\OrderAdminDisplay;
use Rachnix\CheckoutFields\Display\OrderFieldValues;
use Rachnix\CheckoutFields\Rest\OrderFieldsRest;
use Rachnix\CheckoutFields\Admin\BlockCheckoutNotice;
use Rachnix\CheckoutFields\Checkout\PreviewMode;
use Rachnix\CheckoutFields\Rest\PublishController;
use Rachnix\CheckoutFields\Service\PreviewService;
use Rachnix\CheckoutFields\Checkout\Blocks\BlockFieldSupport;
use Rachnix\CheckoutFields\Checkout\Blocks\BlocksCheckoutIntegration;
use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Checkout\CheckoutStyles;
use Rachnix\CheckoutFields\Repository\SettingsRepository;
use Rachnix\CheckoutFields\Rest\SettingsController;
use Rachnix\CheckoutFields\Service\SettingsService;
use Rachnix\CheckoutFields\Checkout\Classic\ClassicCheckoutIntegration;
use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Checkout\WooCoreFieldCatalog;
use Rachnix\CheckoutFields\Contracts\CoreFieldCatalogInterface;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Rest\CoreFieldsController;
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
use Rachnix\CheckoutFields\Core\AuditLogPurger;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;
use Rachnix\CheckoutFields\Rest\FieldsController;
use Rachnix\CheckoutFields\Rest\RestRoutes;
use Rachnix\CheckoutFields\Rest\SectionsController;
use Rachnix\CheckoutFields\Security\Capabilities;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Security\RequestContext;
use Rachnix\CheckoutFields\Service\FieldService;
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
				$registry = new FieldTypeRegistry(
					static function ( FieldTypeRegistry $registry ): void {
						/**
						 * Register extra field types. Fires once, on the first type lookup (not at
						 * plugins_loaded), so add-ons loading after this plugin can still hook in.
						 *
						 * @param FieldTypeRegistry $registry Call `$registry->register( new MyType() )`.
						 */
						do_action( 'rxcf_register_field_types', $registry );
					}
				);
				$registry->register_free_types();
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
			FieldRepository::class,
			static fn( Container $c ) => new FieldRepository(
				self::wpdb(),
				$c->get( TableNames::class ),
				new FieldCache( 'rxcf_field_rows_v1' ),
				$c->get( Logger::class )
			)
		);
		// Same shared instance, so a preview shown through it reaches every reader.
		$c->set( FieldRepositoryInterface::class, static fn( Container $c ) => $c->get( FieldRepository::class ) );

		$c->set( RequestContext::class, static fn() => new RequestContext() );
		$c->set(
			AuditLogRepository::class,
			static fn( Container $c ) => new AuditLogRepository(
				self::wpdb(),
				$c->get( TableNames::class ),
				$c->get( RequestContext::class ),
				$c->get( Logger::class )
			)
		);
		$c->set( CoreFieldCatalogInterface::class, static fn() => new WooCoreFieldCatalog() );
		$c->set(
			FieldService::class,
			static fn( Container $c ) => new FieldService(
				$c->get( FieldRepositoryInterface::class ),
				$c->get( AuditLogRepository::class ),
				$c->get( FieldTypeRegistry::class ),
				$c->get( PatternLibrary::class ),
				$c->get( CoreFieldCatalogInterface::class )
			)
		);

		$c->set( PreviewService::class, static fn( Container $c ) => new PreviewService( $c->get( FieldService::class ) ) );
		$c->set( PreviewMode::class, static fn( Container $c ) => new PreviewMode( $c->get( PreviewService::class ), $c->get( FieldRepository::class ) ) );
		$c->set( AdminPage::class, fn( Container $c ) => new AdminPage( $this->plugin_file, $c->get( CheckoutDetector::class ), $c->get( BlockFieldSupport::class ), $c->get( SettingsRepository::class ) ) );

		$c->set( SettingsRepository::class, static fn() => new SettingsRepository() );
		$c->set( SettingsService::class, static fn( Container $c ) => new SettingsService( $c->get( SettingsRepository::class ), $c->get( AuditLogRepository::class ) ) );
		$c->set( CheckoutStyles::class, fn( Container $c ) => new CheckoutStyles( $c->get( SettingsRepository::class ), $this->plugin_file ) );

		$c->set( StringTranslations::class, static fn( Container $c ) => new StringTranslations( $c->get( FieldRepositoryInterface::class ) ) );
		$c->set( TextDomain::class, fn() => new TextDomain( $this->plugin_file ) );
		$c->set(
			ImportExportService::class,
			static fn( Container $c ) => new ImportExportService( $c->get( FieldService::class ), $c->get( SettingsService::class ), $c->get( AuditLogRepository::class ) )
		);

		$c->set( OrderDataWriter::class, static fn() => new OrderDataWriter() );
		$c->set(
			OrderFieldValues::class,
			static fn( Container $c ) => new OrderFieldValues(
				$c->get( FieldRepositoryInterface::class ),
				$c->get( FieldTypeRegistry::class ),
				$c->get( OrderDataWriter::class ),
				$c->get( StringTranslations::class )
			)
		);
		$c->set( OrderAdminDisplay::class, static fn( Container $c ) => new OrderAdminDisplay( $c->get( OrderFieldValues::class ), $c->get( FieldRepositoryInterface::class ) ) );
		$c->set( CustomerDisplay::class, static fn( Container $c ) => new CustomerDisplay( $c->get( OrderFieldValues::class ) ) );
		$c->set( EmailDisplay::class, static fn( Container $c ) => new EmailDisplay( $c->get( OrderFieldValues::class ) ) );
		$c->set( BlockFieldVisibility::class, static fn( Container $c ) => new BlockFieldVisibility( $c->get( FieldRepositoryInterface::class ) ) );
		$c->set( OrderFieldsRest::class, static fn( Container $c ) => new OrderFieldsRest( $c->get( OrderFieldValues::class ) ) );
		$c->set( PrivacyIntegration::class, static fn( Container $c ) => new PrivacyIntegration( $c->get( OrderFieldValues::class ), $c->get( FieldRepositoryInterface::class ), $c->get( OrderDataWriter::class ) ) );

		$c->set( CheckoutDetector::class, static fn() => new CheckoutDetector() );
		$c->set( BlockFieldSupport::class, static fn() => new BlockFieldSupport() );
		$c->set(
			BlocksCheckoutIntegration::class,
			static fn( Container $c ) => new BlocksCheckoutIntegration(
				$c->get( FieldRepositoryInterface::class ),
				$c->get( FieldTypeRegistry::class ),
				$c->get( BlockFieldSupport::class ),
				$c->get( CheckoutDetector::class ),
				$c->get( Sanitizer::class ),
				$c->get( ValidationService::class ),
				$c->get( Logger::class ),
				$c->get( StringTranslations::class )
			)
		);
		$c->set(
			BlockCheckoutNotice::class,
			static fn( Container $c ) => new BlockCheckoutNotice(
				$c->get( FieldRepositoryInterface::class ),
				$c->get( FieldTypeRegistry::class ),
				$c->get( BlockFieldSupport::class ),
				$c->get( CheckoutDetector::class )
			)
		);
		$c->set(
			ClassicCheckoutIntegration::class,
			static fn( Container $c ) => new ClassicCheckoutIntegration(
				$c->get( FieldRepositoryInterface::class ),
				$c->get( FieldTypeRegistry::class ),
				$c->get( Sanitizer::class ),
				$c->get( ValidationService::class ),
				$c->get( OrderDataWriter::class ),
				$c->get( StringTranslations::class )
			)
		);

		$c->set( Capabilities::class, static fn() => new Capabilities() );
		$c->set( AuditLogPurger::class, static fn( Container $c ) => new AuditLogPurger( $c->get( AuditLogRepository::class ) ) );
		$c->set(
			RestRoutes::class,
			static fn( Container $c ) => new RestRoutes(
				static fn(): array => array(
					new FieldsController(
						$c->get( FieldService::class ),
						$c->get( FieldTypeRegistry::class ),
						$c->get( PatternLibrary::class ),
						$c->get( BlockFieldSupport::class ),
						$c->get( Logger::class )
					),
					new SectionsController( $c->get( FieldService::class ), $c->get( Logger::class ) ),
					new CoreFieldsController( $c->get( FieldService::class ), $c->get( Logger::class ) ),
					new SettingsController( $c->get( SettingsService::class ), $c->get( Logger::class ) ),
					new PublishController( $c->get( FieldService::class ), $c->get( PreviewService::class ), $c->get( Logger::class ) ),
					new ToolsController( $c->get( ImportExportService::class ), $c->get( FieldService::class ), $c->get( SettingsService::class ), $c->get( Logger::class ) ),
				)
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
		return array(
			$this->container->get( Capabilities::class ),
			$this->container->get( AuditLogPurger::class ),
			$this->container->get( RestRoutes::class ),
			// Hooks only; field definitions are read when WooCommerce builds the checkout.
			$this->container->get( ClassicCheckoutIntegration::class ),
			$this->container->get( CheckoutDetector::class ),
			$this->container->get( BlocksCheckoutIntegration::class ),
			$this->container->get( BlockCheckoutNotice::class ),
			$this->container->get( CheckoutStyles::class ),
			$this->container->get( PreviewMode::class ),
			$this->container->get( AdminPage::class ),
			$this->container->get( OrderAdminDisplay::class ),
			$this->container->get( CustomerDisplay::class ),
			$this->container->get( EmailDisplay::class ),
			$this->container->get( BlockFieldVisibility::class ),
			$this->container->get( OrderFieldsRest::class ),
			$this->container->get( PrivacyIntegration::class ),
			$this->container->get( StringTranslations::class ),
			$this->container->get( TextDomain::class ),
		);
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
