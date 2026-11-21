<?php
/**
 * Classic (shortcode) checkout.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout\Classic;

use Rachnix\CheckoutFields\Checkout\OrderDataWriter;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\Domain\Width;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\I18n\StringTranslations;
use Rachnix\CheckoutFields\Service\Sanitizer;
use Rachnix\CheckoutFields\Service\ValidationService;

defined( 'ABSPATH' ) || exit;

/**
 * Edits WooCommerce's core fields and adds custom fields through `woocommerce_checkout_fields`,
 * so WooCommerce renders them with `woocommerce_form_field()` and they look like the theme's.
 *
 * Custom fields are submitted as `rxcf_{key}`. Without the prefix WooCommerce would also store
 * any billing_* / shipping_* key as order meta and copy it to the customer's profile.
 *
 * WooCommerce reports missing required fields (its own message style); ValidationService adds
 * format and rule errors. Values are saved through OrderDataWriter.
 */
final class ClassicCheckoutIntegration implements Hookable {

	public const INPUT_PREFIX = 'rxcf_';

	/** Our section => WooCommerce fieldset. Contact fields (a block checkout section) join billing. */
	private const FIELDSETS = array(
		'billing'  => 'billing',
		'shipping' => 'shipping',
		'order'    => 'order',
		'contact'  => 'billing',
	);

	/** Our type => woocommerce_form_field() type. */
	private const FORM_TYPES = array(
		'text'     => 'text',
		'textarea' => 'textarea',
		'email'    => 'email',
		'phone'    => 'tel',
		'number'   => 'number',
		'password' => 'password',
		'select'   => 'select',
		'radio'    => 'radio',
		'checkbox' => 'checkbox',
		'date'     => 'date',
		'hidden'   => 'hidden',
	);

	private const WIDTH_CLASSES = array( 'form-row-first', 'form-row-last', 'form-row-wide' );

	/**
	 * @param FieldRepositoryInterface $fields    Field definitions.
	 * @param FieldTypeRegistry        $types     Field types.
	 * @param Sanitizer                $sanitizer Value sanitiser.
	 * @param ValidationService        $validator Value validator.
	 * @param OrderDataWriter          $writer    Order storage.
	 * @param StringTranslations|null  $i18n      Translations of store-owner texts (WPML, Polylang).
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
		private readonly FieldTypeRegistry $types,
		private readonly Sanitizer $sanitizer,
		private readonly ValidationService $validator,
		private readonly OrderDataWriter $writer,
		private readonly ?StringTranslations $i18n = null,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_fields' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Name of a custom field's input on the classic checkout.
	 *
	 * @param Field $field Custom field.
	 */
	public static function input_name( Field $field ): string {
		return self::INPUT_PREFIX . $field->key;
	}

	/**
	 * @param mixed $fieldsets WooCommerce checkout fields, grouped by fieldset.
	 * @return mixed
	 */
	public function filter_fields( mixed $fieldsets ): mixed {
		if ( ! is_array( $fieldsets ) ) {
			return $fieldsets;
		}

		foreach ( $this->fields->all() as $field ) {
			$field    = $this->i18n?->field( $field ) ?? $field;
			$fieldset = self::FIELDSETS[ $field->section->value ] ?? null;
			if ( null === $fieldset || ! isset( $fieldsets[ $fieldset ] ) || ! is_array( $fieldsets[ $fieldset ] ) ) {
				continue;
			}

			if ( $field->is_core ) {
				$fieldsets[ $fieldset ] = $this->apply_core( $fieldsets[ $fieldset ], $field );
			} elseif ( $field->enabled && $this->types->has( $field->type ) ) {
				$fieldsets[ $fieldset ][ self::input_name( $field ) ] = $this->args( $field, $this->types->get( $field->type ) );
			}
		}

		foreach ( array_unique( self::FIELDSETS ) as $fieldset ) {
			if ( isset( $fieldsets[ $fieldset ] ) && is_array( $fieldsets[ $fieldset ] ) ) {
				$fieldsets[ $fieldset ] = $this->pair_half_widths( $fieldsets[ $fieldset ] );
			}
		}
		return $fieldsets;
	}

	/**
	 * Adds format and rule errors for custom fields. Runs after WooCommerce's own checks.
	 *
	 * @param mixed $data   Posted data, as WooCommerce read it.
	 * @param mixed $errors WP_Error collecting checkout errors.
	 */
	public function validate( mixed $data, mixed $errors ): void {
		if ( ! is_array( $data ) || ! $errors instanceof \WP_Error ) {
			return;
		}

		foreach ( $this->submitted_fields( $data ) as $field ) {
			$field = $this->i18n?->field( $field ) ?? $field; // Messages use the translated label.
			$value = $this->submitted_value( $field );
			if ( null === $value ) {
				continue;
			}
			foreach ( $this->validator->validate( $field, $value ) as $error ) {
				if ( ValidationErrorCode::Required === $error->code ) {
					continue; // WooCommerce has already reported it, in its own words.
				}
				$errors->add( 'validation', esc_html( $error->message( '' !== $field->label ? $field->label : $field->key ) ), array( 'id' => self::input_name( $field ) ) );
			}
		}
	}

	/**
	 * Saves custom field values on the new order. Empty values are not stored.
	 *
	 * @param mixed $order Order being created (not yet saved).
	 * @param mixed $data  Posted data.
	 */
	public function save( mixed $order, mixed $data ): void {
		if ( ! $order instanceof \WC_Order || ! is_array( $data ) ) {
			return;
		}

		foreach ( $this->submitted_fields( $data ) as $field ) {
			$value = $this->submitted_value( $field );
			if ( null !== $value && '' !== $value ) {
				$this->writer->write( $order, $field, $value );
			}
		}
	}

	/**
	 * Custom fields that are part of this submission: enabled, of a known type, and not
	 * in the shipping section unless the order ships to a different address (as WooCommerce decides).
	 *
	 * @param array<mixed> $data Posted data.
	 * @return list<Field>
	 */
	private function submitted_fields( array $data ): array {
		$ships_elsewhere = ! empty( $data['ship_to_different_address'] )
			&& function_exists( 'WC' ) && null !== WC()->cart && WC()->cart->needs_shipping_address();

		$fields = array();
		foreach ( $this->fields->all()->enabled() as $field ) {
			if ( $field->is_core || ! $this->types->has( $field->type ) || ! isset( self::FIELDSETS[ $field->section->value ] ) ) {
				continue;
			}
			if ( Section::Shipping === $field->section->section() && ! $ships_elsewhere ) {
				continue;
			}
			$fields[] = $field;
		}
		return $fields;
	}

	/**
	 * The field's posted value, sanitised by its type.
	 *
	 * @param Field $field Custom field.
	 */
	private function submitted_value( Field $field ): ?string {
		$name = self::input_name( $field );
		// WooCommerce verifies the checkout nonce before these hooks run; the value is sanitised by the field type.
		$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $this->sanitizer->sanitize( $field, $raw );
	}

	/**
	 * woocommerce_form_field() arguments for a custom field.
	 *
	 * @param Field              $field Custom field.
	 * @param FieldTypeInterface $type  Its type.
	 * @return array<string, mixed>
	 */
	private function args( Field $field, FieldTypeInterface $type ): array {
		$slug = $type->slug();
		$args = array(
			'type'              => self::FORM_TYPES[ $slug ] ?? 'text',
			'label'             => 'hidden' === $slug ? '' : $field->label,
			'placeholder'       => $type->supports_placeholder() ? $field->placeholder : '',
			'required'          => $field->settings->required && $type->can_be_required(),
			'class'             => array_merge(
				array( Width::Half === $field->settings->width ? 'form-row-first' : 'form-row-wide', 'rxcf-field', 'rxcf-field-' . $slug ),
				$field->settings->css_classes
			),
			'priority'          => $field->priority,
			'default'           => $field->settings->default_value,
			'custom_attributes' => $this->attributes( $field, $type ),
		);

		if ( $type->supports_options() ) {
			$options = array();
			if ( 'select' === $args['type'] ) {
				// A blank first choice, so nothing is pre-selected unless a default is set.
				$options[''] = '' !== $field->placeholder ? $field->placeholder : __( 'Choose an option', 'rachnix-checkout-fields' );
			}
			foreach ( $field->settings->options as $option ) {
				$options[ $option->value ] = $option->label;
			}
			$args['options'] = $options;
		}

		if ( ! isset( self::FORM_TYPES[ $slug ] ) ) {
			/**
			 * Form type for a field type added by an add-on.
			 *
			 * @param string $form_type woocommerce_form_field() type.
			 * @param string $slug      Field type slug.
			 */
			$form_type    = apply_filters( 'rxcf_classic_form_type', 'text', $slug );
			$args['type'] = is_string( $form_type ) ? $form_type : 'text';
		}

		/**
		 * woocommerce_form_field() arguments for a custom field on the classic checkout.
		 *
		 * @param array<string, mixed> $args  Arguments.
		 * @param Field                $field Field definition.
		 */
		$filtered = apply_filters( 'rxcf_classic_field_args', $args, $field );
		return is_array( $filtered ) ? $filtered : $args;
	}

	/**
	 * HTML limits that mirror the server-side rules, so the browser can help the customer early.
	 * The server still validates everything.
	 *
	 * @param Field              $field Custom field.
	 * @param FieldTypeInterface $type  Its type.
	 * @return array<string, string>
	 */
	private function attributes( Field $field, FieldTypeInterface $type ): array {
		$attributes = array();
		$max_length = $type->max_length();

		foreach ( $field->settings->validation as $rule ) {
			if ( ! in_array( $rule->type, $type->allowed_rules(), true ) ) {
				continue;
			}
			if ( ValidationRuleType::Length === $rule->type && null !== $rule->max ) {
				$max_length = min( $max_length, (int) $rule->max );
			}
			if ( ValidationRuleType::NumberRange === $rule->type ) {
				if ( null !== $rule->min ) {
					$attributes['min'] = (string) $rule->min;
				}
				if ( null !== $rule->max ) {
					$attributes['max'] = (string) $rule->max;
				}
			}
		}

		if ( 'number' === $type->slug() ) {
			$attributes['step'] = 'any';
		} elseif ( ! in_array( $type->slug(), array( 'select', 'radio', 'checkbox', 'date', 'hidden' ), true ) ) {
			$attributes['maxlength'] = (string) $max_length;
		}
		return $attributes;
	}

	/**
	 * Applies a core override to WooCommerce's field: hide it, or change label, placeholder,
	 * required, width, classes and position. Empty label or placeholder keeps WooCommerce's.
	 *
	 * @param array<mixed> $fieldset WooCommerce fields of one fieldset.
	 * @param Field        $field    Core override.
	 * @return array<mixed>
	 */
	private function apply_core( array $fieldset, Field $field ): array {
		if ( ! isset( $fieldset[ $field->key ] ) || ! is_array( $fieldset[ $field->key ] ) ) {
			return $fieldset;
		}
		if ( ! $field->enabled ) {
			unset( $fieldset[ $field->key ] );
			return $fieldset;
		}

		$args = $fieldset[ $field->key ];
		if ( '' !== $field->label ) {
			$args['label'] = $field->label;
		}
		if ( '' !== $field->placeholder ) {
			$args['placeholder'] = $field->placeholder;
		}
		$args['required'] = $field->settings->required;
		$args['priority'] = $field->priority;

		$classes       = is_array( $args['class'] ?? null ) ? array_diff( $args['class'], self::WIDTH_CLASSES ) : array();
		$args['class'] = array_values(
			array_unique(
				array_merge(
					array( Width::Half === $field->settings->width ? 'form-row-first' : 'form-row-wide' ),
					$classes,
					array( 'rxcf-field' ),
					$field->settings->css_classes
				)
			)
		);

		$fieldset[ $field->key ] = $args;
		return $fieldset;
	}

	/**
	 * Half-width fields this plugin controls sit in pairs: the first of a pair gets
	 * form-row-first, the next form-row-last. WooCommerce's untouched fields keep their classes.
	 *
	 * @param array<mixed> $fieldset WooCommerce fields of one fieldset.
	 * @return array<mixed>
	 */
	private function pair_half_widths( array $fieldset ): array {
		$order = array();
		foreach ( $fieldset as $key => $args ) {
			$order[ $key ] = is_array( $args ) && is_numeric( $args['priority'] ?? null ) ? (float) $args['priority'] : 0.0;
		}
		asort( $order );

		$previous_was_first = false;
		foreach ( array_keys( $order ) as $key ) {
			$classes = is_array( $fieldset[ $key ]['class'] ?? null ) ? $fieldset[ $key ]['class'] : array();
			$is_half = array() !== array_intersect( array( 'form-row-first', 'form-row-last' ), $classes );
			$ours    = in_array( 'rxcf-field', $classes, true );

			if ( $is_half && $ours ) {
				$position                  = $previous_was_first ? 'form-row-last' : 'form-row-first';
				$fieldset[ $key ]['class'] = array_values( array_merge( array( $position ), array_diff( $classes, self::WIDTH_CLASSES ) ) );
				$previous_was_first        = ! $previous_was_first;
			} else {
				$previous_was_first = $is_half && in_array( 'form-row-first', $classes, true );
			}
		}
		return $fieldset;
	}
}
