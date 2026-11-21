<?php
/**
 * WPML and Polylang support for the texts store owners type.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\I18n;

use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;

defined( 'ABSPATH' ) || exit;

/**
 * Labels, placeholders and choice labels are registered as translatable strings (named by
 * field key, so names are the same on every site) and translated where customers see them.
 * Without WPML or Polylang everything here is a no-op.
 */
final class StringTranslations implements Hookable {

	public const CONTEXT = 'rachnix-checkout-fields';

	private const POLYLANG_GROUP = 'Rachnix Checkout Fields';

	/**
	 * @param FieldRepositoryInterface $fields Field definitions.
	 * @param string|null              $engine Forces "wpml" or "none" (tests); null detects WPML and Polylang.
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
		private readonly ?string $engine = null,
	) {}

	public function register(): void {
		// Polylang lists only strings registered on the current admin request.
		add_action( 'admin_init', array( $this, 'register_strings' ) );
	}

	public function active(): bool {
		return $this->wpml() || $this->polylang();
	}

	public function register_strings(): void {
		if ( ! $this->active() ) {
			return;
		}
		foreach ( $this->fields->all() as $field ) {
			foreach ( $this->strings( $field ) as $name => $value ) {
				if ( $this->wpml() ) {
					do_action( 'wpml_register_single_string', self::CONTEXT, $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
				}
				if ( $this->polylang() ) {
					pll_register_string( $name, $value, self::POLYLANG_GROUP );
				}
			}
		}
	}

	/**
	 * The field with its texts in the visitor's language.
	 *
	 * @param Field $field Field as stored.
	 */
	public function field( Field $field ): Field {
		if ( ! $this->active() ) {
			return $field;
		}
		$options = array_map(
			fn( FieldOption $option ): FieldOption => new FieldOption(
				$option->value,
				$this->translate( self::option_name( $field, $option ), $option->label )
			),
			$field->settings->options
		);
		return $field->with_texts(
			$this->translate( self::label_name( $field ), $field->label ),
			$this->translate( self::placeholder_name( $field ), $field->placeholder ),
			$options
		);
	}

	/**
	 * Non-empty texts of a field, by string name.
	 *
	 * @param Field $field Field.
	 * @return array<string, string>
	 */
	public function strings( Field $field ): array {
		$strings = array(
			self::label_name( $field )       => $field->label,
			self::placeholder_name( $field ) => $field->placeholder,
		);
		foreach ( $field->settings->options as $option ) {
			$strings[ self::option_name( $field, $option ) ] = $option->label;
		}
		return array_filter( $strings, static fn( string $text ): bool => '' !== $text );
	}

	/**
	 * @param string $name  String name.
	 * @param string $value Original text.
	 */
	private function translate( string $name, string $value ): string {
		if ( '' === $value ) {
			return $value;
		}
		if ( $this->wpml() ) {
			$translated = apply_filters( 'wpml_translate_single_string', $value, self::CONTEXT, $name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
			return is_string( $translated ) ? $translated : $value;
		}
		if ( $this->polylang() ) {
			$translated = pll__( $value );
			return is_string( $translated ) ? $translated : $value;
		}
		return $value;
	}

	private function wpml(): bool {
		return null === $this->engine ? defined( 'ICL_SITEPRESS_VERSION' ) : 'wpml' === $this->engine;
	}

	private function polylang(): bool {
		return null === $this->engine && function_exists( 'pll_register_string' ) && function_exists( 'pll__' );
	}

	/**
	 * @param Field $field Field.
	 */
	private static function label_name( Field $field ): string {
		return "{$field->section->value}.{$field->key}.label";
	}

	/**
	 * @param Field $field Field.
	 */
	private static function placeholder_name( Field $field ): string {
		return "{$field->section->value}.{$field->key}.placeholder";
	}

	/**
	 * @param Field       $field  Field.
	 * @param FieldOption $option Choice.
	 */
	private static function option_name( Field $field, FieldOption $option ): string {
		return "{$field->section->value}.{$field->key}.option.{$option->value}";
	}
}
