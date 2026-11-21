<?php
/**
 * The "System info" text a store owner pastes into a bug report.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Plugin;
use Rachnix\CheckoutFields\Repository\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Versions and setup only, so a report can be posted publicly: no site address, no
 * user or customer data, and no field labels (store owners may have typed anything).
 * Fields are counted by section and type; custom fields are named by key only.
 */
final class SystemInfo {

	/**
	 * @param FieldService       $fields   Field definitions.
	 * @param CheckoutDetector   $detector Which checkout the store uses.
	 * @param SettingsRepository $settings Plugin settings.
	 * @param string             $db_version_option Schema version option.
	 */
	public function __construct(
		private readonly FieldService $fields,
		private readonly CheckoutDetector $detector,
		private readonly SettingsRepository $settings,
		private readonly string $db_version_option = Plugin::DB_VERSION_OPTION,
	) {}

	/**
	 * @return array<string, array<string, string>> Group title => label => value.
	 */
	public function collect(): array {
		global $wpdb;
		$theme = wp_get_theme();

		$groups = array(
			'Plugin'      => array(
				'Version'             => Plugin::VERSION,
				'Database version'    => self::scalar( get_option( $this->db_version_option, '' ) ),
				'Checkout'            => $this->detector->uses_block_checkout() ? 'Checkout block' : 'Classic (shortcode)',
				'Style mode'          => $this->settings->style()->mode->value,
				'Setup wizard done'   => self::yes_no( $this->settings->wizard_completed() ),
				'Delete on uninstall' => self::yes_no( $this->settings->delete_data_on_uninstall() ),
			),
			'Environment' => array(
				'WordPress'    => get_bloginfo( 'version' ),
				'WooCommerce'  => defined( 'WC_VERSION' ) ? (string) WC_VERSION : 'not active',
				'PHP'          => PHP_VERSION,
				'Database'     => $wpdb->db_server_info(),
				'Multisite'    => self::yes_no( is_multisite() ),
				'HPOS'         => self::yes_no( self::hpos_enabled() ),
				'Object cache' => self::yes_no( true === wp_using_ext_object_cache() ), // Null when no cache was ever set up.
				'Language'     => get_locale(),
				'Theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( wp_is_block_theme() ? ' (block theme)' : '' ),
			),
			'Fields'      => $this->field_summary(),
			'Plugins'     => $this->active_plugins(),
		);

		return $groups;
	}

	/**
	 * Plain text for pasting into a support topic or issue.
	 */
	public function as_text(): string {
		$lines = array();
		foreach ( $this->collect() as $group => $rows ) {
			$lines[] = '### ' . $group;
			foreach ( $rows as $label => $value ) {
				$lines[] = $label . ': ' . $value;
			}
			$lines[] = '';
		}
		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * @return array<string, string>
	 */
	private function field_summary(): array {
		$groups = array();
		foreach ( $this->fields->list() as $field ) {
			$group              = $field->section->value . ( $field->is_core ? ' (WooCommerce fields changed)' : ' (custom)' );
			$groups[ $group ][] = $field->key
				. ( $field->is_core ? '' : ' [' . $field->type . ']' )
				. ( $field->enabled ? '' : ' (hidden)' );
		}
		ksort( $groups );
		$rows = array_map( static fn( array $keys ): string => implode( ', ', $keys ), $groups );
		return array() === $rows ? array( 'Fields' => 'none' ) : $rows;
	}

	/**
	 * @return array<string, string>
	 */
	private function active_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$rows = array();
		foreach ( get_plugins() as $file => $data ) {
			if ( in_array( $file, $active, true ) ) {
				$rows[ (string) $data['Name'] ] = (string) $data['Version'];
			}
		}
		ksort( $rows );
		return $rows;
	}

	private static function hpos_enabled(): bool {
		$util = '\Automattic\WooCommerce\Utilities\OrderUtil';
		return class_exists( $util ) && true === $util::custom_orders_table_usage_is_enabled();
	}

	private static function yes_no( bool $value ): string {
		return $value ? 'yes' : 'no';
	}

	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
