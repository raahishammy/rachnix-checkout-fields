<?php
/**
 * Export and import of field settings as signed JSON.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Domain\AuditAction;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\StyleSettings;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Plugin;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The file holds the field definitions and the style, plus an HMAC-SHA-256 signature keyed
 * with this site's auth salt. A file from another site, or one edited by hand, is "unsigned":
 * it can still be imported, but only after the owner has seen what will change and confirmed.
 *
 * Importing replaces the current setup: fields are matched by key (stable across sites),
 * fields missing from the file are removed, WooCommerce fields go back to their defaults
 * unless the file changes them. Field changes run through FieldService::apply_batch(), so
 * they get the same validation and the same all-or-nothing transaction as "Publish changes".
 */
final class ImportExportService {

	public const FORMAT = 'rachnix-checkout-fields';

	public const VERSION = 1;

	public const MAX_BYTES = 1048576;

	public const MAX_FIELDS = 500;

	/**
	 * @param FieldService       $fields   Field changes.
	 * @param SettingsService    $settings Settings changes.
	 * @param AuditLogRepository $audit    Audit log.
	 */
	public function __construct(
		private readonly FieldService $fields,
		private readonly SettingsService $settings,
		private readonly AuditLogRepository $audit,
	) {}

	/**
	 * @return array<string, mixed> The export file.
	 */
	public function export(): array {
		$payload              = array(
			'format'         => self::FORMAT,
			'version'        => self::VERSION,
			'plugin_version' => Plugin::VERSION,
			'exported_at'    => gmdate( 'c' ),
			'fields'         => array_map( static fn( Field $field ): array => $field->to_array(), $this->fields->list()->to_list() ),
			'style'          => $this->settings->style()->to_array(),
		);
		$payload['signature'] = $this->signature( $payload );
		return $payload;
	}

	/**
	 * What importing the file would do, without changing anything.
	 *
	 * @param array<mixed> $file    Decoded file.
	 * @param int          $user_id Acting user.
	 * @return array{signed: bool, added: list<string>, changed: list<string>, removed: list<string>}
	 * @throws InvalidFieldInput When the file is malformed or a field in it is not acceptable.
	 * @throws DatabaseError     When the database fails.
	 */
	public function preview( array $file, int $user_id ): array {
		[ $operations, $summary ] = $this->plan( $file );
		$this->fields->preview( $operations, $user_id ); // Same validation as the real import.
		return array( 'signed' => $this->is_signed( $file ) ) + $summary;
	}

	/**
	 * Imports the file.
	 *
	 * @param array<mixed> $file             Decoded file.
	 * @param int          $user_id          Acting user.
	 * @param bool         $can_edit_css     Whether the user may change custom CSS.
	 * @param bool         $confirm_unsigned The owner confirmed importing an unsigned file.
	 * @return array{signed: bool, added: list<string>, changed: list<string>, removed: list<string>}
	 * @throws InvalidFieldInput When the file is unsigned and not confirmed, malformed, or not acceptable.
	 * @throws DatabaseError     When the database fails (nothing is changed).
	 */
	public function import( array $file, int $user_id, bool $can_edit_css, bool $confirm_unsigned ): array {
		$signed = $this->is_signed( $file );
		if ( ! $signed && ! $confirm_unsigned ) {
			throw InvalidFieldInput::with( array( 'file' => __( 'This file was not exported from this site, or it was changed after export. Check what will change, then confirm.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		[ $operations, $summary ] = $this->plan( $file );

		$style = null;
		if ( is_array( $file['style'] ?? null ) ) {
			$changes = $file['style'];
			if ( ! $can_edit_css ) {
				unset( $changes['custom_css'] ); // Kept as it is; only users who can edit CSS may change it.
			}
			$style = $this->settings->prepare_style( $changes, $can_edit_css );
		}

		$this->fields->apply_batch( $operations, $user_id );
		if ( $style instanceof StyleSettings ) {
			$this->settings->save_prepared_style( $style, $user_id, AuditAction::Import );
		}
		$this->audit->record( AuditAction::Import, 'settings', 'import', null, $summary + array( 'signed' => $signed ), $user_id );

		return array( 'signed' => $signed ) + $summary;
	}

	/**
	 * Whether the file carries a valid signature from this site.
	 *
	 * @param array<mixed> $file Decoded file.
	 */
	public function is_signed( array $file ): bool {
		$signature = $file['signature'] ?? null;
		if ( ! is_string( $signature ) || 64 !== strlen( $signature ) ) {
			return false;
		}
		unset( $file['signature'] );
		return hash_equals( $this->signature( $file ), $signature );
	}

	/**
	 * Operations that turn the current setup into the file's, plus a summary by field key.
	 *
	 * @param array<mixed> $file Decoded file.
	 * @return array{0: list<array<string, mixed>>, 1: array{added: list<string>, changed: list<string>, removed: list<string>}}
	 * @throws InvalidFieldInput When the file is malformed.
	 */
	private function plan( array $file ): array {
		$entries = $this->entries( $file );

		$current_custom = array();
		$current_core   = array();
		foreach ( $this->fields->list() as $field ) {
			if ( $field->is_core ) {
				$current_core[ "{$field->section->value}:{$field->key}" ] = $field;
			} else {
				$current_custom[ $field->key ] = $field;
			}
		}

		$deletes  = array();
		$others   = array();
		$summary  = array(
			'added'   => array(),
			'changed' => array(),
			'removed' => array(),
		);
		$in_file  = array();
		$core_ids = array();

		foreach ( $entries as $entry ) {
			$section = (string) $entry['section'];
			$key     = (string) $entry['key'];

			if ( true === ( $entry['is_core'] ?? false ) ) {
				$core_ids[ "{$section}:{$key}" ] = true;
				$others[]                        = array(
					'op'      => 'core',
					'section' => $section,
					'key'     => $key,
					'changes' => self::pick( $entry, array( 'label', 'placeholder', 'priority', 'enabled', 'settings' ) ),
				);
				$summary['changed'][]            = $key;
				continue;
			}

			$in_file[ $key ] = true;
			$definition      = self::pick( $entry, array( 'section', 'key', 'type', 'label', 'placeholder', 'priority', 'enabled', 'settings' ) );
			$existing        = $current_custom[ $key ] ?? null;

			if ( null === $existing ) {
				$others[]           = array(
					'op'    => 'create',
					'field' => $definition,
				);
				$summary['added'][] = $key;
			} elseif ( self::differs( $existing->to_array(), $definition ) ) {
				$others[]             = array(
					'op'      => 'update',
					'uuid'    => $existing->uuid,
					'changes' => $definition,
				);
				$summary['changed'][] = $key;
			}
		}

		foreach ( $current_custom as $key => $field ) {
			if ( ! isset( $in_file[ $key ] ) ) {
				$deletes[]            = array(
					'op'   => 'delete',
					'uuid' => $field->uuid,
				);
				$summary['removed'][] = (string) $key;
			}
		}
		foreach ( $current_core as $id => $field ) {
			if ( ! isset( $core_ids[ $id ] ) ) {
				$deletes[]            = array(
					'op'   => 'delete',
					'uuid' => $field->uuid,
				);
				$summary['changed'][] = $field->key; // Back to WooCommerce's defaults.
			}
		}

		return array( array_merge( $deletes, $others ), $summary );
	}

	/**
	 * The file's field entries, after checking its overall shape.
	 *
	 * @param array<mixed> $file Decoded file.
	 * @return list<array<string, mixed>>
	 * @throws InvalidFieldInput When the file is not one of ours or is malformed.
	 */
	private function entries( array $file ): array {
		if ( self::FORMAT !== ( $file['format'] ?? null ) || ! is_int( $file['version'] ?? null ) || $file['version'] > self::VERSION || $file['version'] < 1 ) {
			throw InvalidFieldInput::with( array( 'file' => __( 'This is not a Rachnix Checkout Field Editor export, or it comes from a newer version of the plugin.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}
		$fields = $file['fields'] ?? null;
		if ( ! is_array( $fields ) || ! array_is_list( $fields ) || count( $fields ) > self::MAX_FIELDS ) {
			/* translators: %d: maximum number of fields. */
			throw InvalidFieldInput::with( array( 'file.fields' => sprintf( __( 'The file must list at most %d fields.', 'rachnix-checkout-fields' ), self::MAX_FIELDS ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		$entries = array();
		$seen    = array();
		foreach ( $fields as $index => $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['key'] ?? null ) || ! is_string( $entry['section'] ?? null ) || ( isset( $entry['type'] ) && ! is_string( $entry['type'] ) ) ) {
				throw InvalidFieldInput::with( array( "file.fields.{$index}" => __( 'Each field needs a key, a section and a type.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
			}
			$id = ( true === ( $entry['is_core'] ?? false ) ? 'core:' : '' ) . $entry['section'] . ':' . $entry['key'];
			if ( isset( $seen[ $id ] ) ) {
				throw InvalidFieldInput::with( array( "file.fields.{$index}" => __( 'The file lists the same field twice.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
			}
			$seen[ $id ] = true;
			$entries[]   = $entry;
		}
		return $entries;
	}

	/**
	 * @param array<mixed> $payload File without its signature.
	 */
	private function signature( array $payload ): string {
		$key = hash_hmac( 'sha256', 'rxcf-export-v1', wp_salt( 'auth' ) );
		return hash_hmac( 'sha256', (string) wp_json_encode( self::canonical( $payload ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $key );
	}

	/**
	 * Sorts object keys at every level, so the signature does not depend on key order.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function canonical( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$value = array_map( array( self::class, 'canonical' ), $value );
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}
		return $value;
	}

	/**
	 * @param array<mixed> $entry Entry.
	 * @param list<string> $keys  Keys to keep.
	 * @return array<string, mixed>
	 */
	private static function pick( array $entry, array $keys ): array {
		$picked = array();
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $entry ) ) {
				$picked[ $key ] = $entry[ $key ];
			}
		}
		return $picked;
	}

	/**
	 * @param array<string, mixed> $current Current definition.
	 * @param array<string, mixed> $wanted  Definition from the file.
	 */
	private static function differs( array $current, array $wanted ): bool {
		foreach ( $wanted as $key => $value ) {
			if ( wp_json_encode( self::canonical( $current[ $key ] ?? null ) ) !== wp_json_encode( self::canonical( $value ) ) ) {
				return true;
			}
		}
		return false;
	}
}
