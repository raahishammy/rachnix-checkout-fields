<?php
/**
 * Block checkout, through WooCommerce's Additional Checkout Fields API.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Checkout\Blocks;

use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\I18n\StringTranslations;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\Sanitizer;
use Rachnix\CheckoutFields\Service\ValidationService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers enabled custom fields with woocommerce_register_additional_checkout_field() on
 * `woocommerce_init`, but only when the store's checkout uses the Checkout block: registered
 * contact and address fields also appear on My Account forms, which a classic-checkout store
 * should not get.
 *
 * WooCommerce renders, stores (order meta `_wc_other/rachnix/{key}`, `_wc_billing/...`,
 * `_wc_shipping/...`) and displays the values. Our sanitiser runs on text-like values and
 * ValidationService runs on every value; WooCommerce's own type checks still apply.
 */
final class BlocksCheckoutIntegration implements Hookable {

	public const ID_NAMESPACE = 'rachnix';

	/** Our section => Additional Checkout Fields location. Billing and shipping share "address". */
	private const LOCATIONS = array(
		'contact'  => 'contact',
		'order'    => 'order',
		'billing'  => 'address',
		'shipping' => 'address',
	);

	/**
	 * @param FieldRepositoryInterface $fields    Field definitions.
	 * @param FieldTypeRegistry        $types     Field types.
	 * @param BlockFieldSupport        $support   Block type support.
	 * @param CheckoutDetector         $detector  Which checkout is in use.
	 * @param Sanitizer                $sanitizer Value sanitiser.
	 * @param ValidationService        $validator Value validator.
	 * @param Logger                   $logger    Error log.
	 * @param StringTranslations|null  $i18n      Translations of store-owner texts (WPML, Polylang).
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
		private readonly FieldTypeRegistry $types,
		private readonly BlockFieldSupport $support,
		private readonly CheckoutDetector $detector,
		private readonly Sanitizer $sanitizer,
		private readonly ValidationService $validator,
		private readonly Logger $logger,
		private readonly ?StringTranslations $i18n = null,
	) {}

	public function register(): void {
		add_action( 'woocommerce_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Additional Checkout Fields id of a custom field.
	 *
	 * @param Field $field Custom field.
	 */
	public static function field_id( Field $field ): string {
		return self::ID_NAMESPACE . '/' . $field->key;
	}

	public function register_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$registrations = array();
		foreach ( $this->fields->all()->enabled() as $field ) {
			$options = $this->options_for( $field );
			if ( null !== $options ) {
				$registrations[] = array( $field, $options );
			}
		}
		if ( array() === $registrations ) {
			return; // Nothing to register: skip the checkout detection (a query on block themes).
		}

		/**
		 * Whether to register fields with the block checkout. Defaults to true only when
		 * the checkout page (or, on block themes, the checkout template) uses the Checkout block.
		 *
		 * @param bool $register Default.
		 */
		if ( true !== apply_filters( 'rxcf_register_block_checkout_fields', $this->detector->uses_block_checkout() ) ) {
			return;
		}

		foreach ( $registrations as [ $field, $options ] ) {
			try {
				woocommerce_register_additional_checkout_field( $options );
			} catch ( \Throwable $e ) {
				$this->logger->error(
					'Could not register a block checkout field.',
					array(
						'field'  => $field->key,
						'reason' => $e->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Registration options for a field, or null when the block checkout cannot show it.
	 *
	 * @param Field $field Field definition.
	 * @return array<string, mixed>|null
	 */
	public function options_for( Field $field ): ?array {
		// Translated texts; the validate callback below keeps this copy, so messages are translated too.
		$field    = $this->i18n?->field( $field ) ?? $field;
		$location = self::LOCATIONS[ $field->section->value ] ?? null;
		if ( $field->is_core || ! $field->enabled || null === $location || ! $this->types->has( $field->type ) ) {
			return null;
		}
		$native = $this->support->native_type( $field->type );
		if ( null === $native ) {
			return null;
		}

		$type    = $this->types->get( $field->type );
		$options = array(
			'id'                         => self::field_id( $field ),
			'label'                      => '' !== $field->label ? $field->label : $field->key,
			'location'                   => $location,
			'type'                       => $native,
			'required'                   => $field->settings->required && $type->can_be_required(),
			'show_in_order_confirmation' => $field->settings->display->thank_you,
			'validate_callback'          => fn( mixed $value ): bool|\WP_Error => $this->validate( $field, $value ),
		);

		if ( 'text' === $native ) {
			// Checkbox, select and date keep WooCommerce's sanitiser: the Store API checks the
			// sanitised value against the field's schema (a checkbox must stay a boolean).
			$options['sanitize_callback'] = fn( mixed $value ): string => (string) $this->sanitizer->sanitize( $field, $value );
			$options['attributes']        = array_filter(
				array(
					'maxLength'    => $this->max_length( $field ),
					'autocomplete' => $this->support->autocomplete( $field->type ),
				),
				static fn( $value ): bool => null !== $value
			);
		}

		if ( 'select' === $native ) {
			$options['options'] = array_map(
				static fn( $option ): array => array(
					'value' => $option->value,
					'label' => $option->label,
				),
				$field->settings->options
			);
			if ( '' !== $field->placeholder ) {
				$options['placeholder'] = $field->placeholder;
			}
		}

		/**
		 * Additional Checkout Fields registration options for a field.
		 *
		 * @param array<string, mixed> $options Options.
		 * @param Field                $field   Field definition.
		 */
		$filtered = apply_filters( 'rxcf_block_field_options', $options, $field );
		return is_array( $filtered ) ? $filtered : $options;
	}

	/**
	 * Validate callback: the value WooCommerce sanitised, checked by ValidationService.
	 * Messages are plain text; the block renders them as text.
	 *
	 * @param Field $field Field definition.
	 * @param mixed $value Submitted value (boolean for checkboxes).
	 */
	public function validate( Field $field, mixed $value ): bool|\WP_Error {
		$clean  = $this->sanitizer->sanitize( $field, $value ) ?? '';
		$errors = $this->validator->validate( $field, $clean );
		if ( array() === $errors ) {
			return true;
		}

		$wp_error = new \WP_Error();
		foreach ( $errors as $error ) {
			$wp_error->add( 'rxcf_' . $error->code->value, $error->message( '' !== $field->label ? $field->label : $field->key ) );
		}
		return $wp_error;
	}

	/**
	 * WooCommerce storage group to read a block checkout value from: billing-section fields
	 * from the billing address, shipping-section fields from the shipping address, all others
	 * from "other". (Address fields are saved for both addresses; this picks the one that matters.)
	 *
	 * @param Field $field Field definition.
	 * @return string "billing", "shipping" or "other".
	 */
	public static function storage_group( Field $field ): string {
		return match ( $field->section->section() ) {
			Section::Billing  => 'billing',
			Section::Shipping => 'shipping',
			default           => 'other',
		};
	}

	/**
	 * @param Field $field Field definition.
	 */
	private function max_length( Field $field ): int {
		$max = $this->types->get( $field->type )->max_length();
		foreach ( $field->settings->validation as $rule ) {
			if ( ValidationRuleType::Length === $rule->type && null !== $rule->max ) {
				$max = min( $max, (int) $rule->max );
			}
		}
		return $max;
	}
}
