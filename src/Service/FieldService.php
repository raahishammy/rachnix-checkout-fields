<?php
/**
 * Creates, changes and removes field definitions.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Contracts\CoreFieldCatalogInterface;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Domain\CoreFieldDefault;
use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Domain\AuditAction;
use Rachnix\CheckoutFields\Domain\DisplayFlags;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldCollection;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\Patterns;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\Domain\Width;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\FieldNotFound;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Every admin change to field definitions goes through here: the REST API now,
 * import later. Input is validated and sanitised again even when the REST schema
 * already checked its shape, and every change is written to the audit log.
 */
final class FieldService {

	public const MAX_OPTIONS = 100;

	public const MAX_RULES = 10;

	public const MAX_CSS_CLASSES = 10;

	public const MAX_ORDER_LIST_FIELDS = 2;

	/**
	 * WooCommerce's own checkout fields and request names. A custom field with one of
	 * these keys could overwrite checkout data, so they are reserved. WooCommerce's
	 * own fields are edited as core fields (step 5), not created as custom ones.
	 */
	public const RESERVED_KEYS = array(
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_country',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_phone',
		'billing_email',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_country',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		'shipping_phone',
		'order_comments',
		'email',
		'account_username',
		'account_password',
		'createaccount',
		'ship_to_different_address',
		'payment_method',
		'shipping_method',
		'terms',
		'coupon_code',
		'post_data',
		'woocommerce_checkout_update_totals',
		'action',
	);

	/** Type slug stored for WooCommerce core field overrides. Never registered as a field type. */
	public const CORE_TYPE = 'core';

	/** Core fields the checkout cannot work without: they cannot be hidden. */
	public const NOT_HIDEABLE = array( 'billing_email', 'billing_country', 'shipping_country' );

	/** Core fields that must stay required (order emails and guest checkout need an address). */
	public const ALWAYS_REQUIRED = array( 'billing_email' );

	/**
	 * @param FieldRepositoryInterface  $fields   Field storage.
	 * @param AuditLogRepository        $audit    Audit log.
	 * @param FieldTypeRegistry         $types    Field types.
	 * @param PatternLibrary            $patterns Curated patterns.
	 * @param CoreFieldCatalogInterface $core     WooCommerce's default fields.
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
		private readonly AuditLogRepository $audit,
		private readonly FieldTypeRegistry $types,
		private readonly PatternLibrary $patterns,
		private readonly CoreFieldCatalogInterface $core,
	) {}

	/**
	 * WooCommerce's default fields, for the admin.
	 *
	 * @return list<CoreFieldDefault>
	 */
	public function core_defaults(): array {
		return $this->core->all();
	}

	/**
	 * Edits, hides or moves a WooCommerce core field. The first change creates an
	 * override that starts from WooCommerce's defaults; later changes update it.
	 * Deleting the override (DELETE /fields/{uuid}) restores WooCommerce's field.
	 *
	 * @param SectionKey           $section Section.
	 * @param string               $key     WooCommerce field key.
	 * @param array<string, mixed> $changes label, placeholder, priority, enabled, settings.required/width/css_classes.
	 * @param int                  $user_id Acting user.
	 * @throws InvalidFieldInput When the key is not a core field of the section, or a change is not allowed.
	 * @throws DatabaseError     When it cannot be stored.
	 */
	public function customize_core( SectionKey $section, string $key, array $changes, int $user_id ): Field {
		$default = $this->core->find( $section, $key );
		if ( null === $default ) {
			throw InvalidFieldInput::with( array( 'key' => __( 'This is not a WooCommerce field of this section.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		$existing = $this->fields->all()->find_by_key( $section, $key );
		if ( null !== $existing && ! $existing->is_core ) {
			// Possible when another plugin adds an address field after a custom field took its key.
			throw InvalidFieldInput::with( array( 'key' => __( 'A custom field already uses this key.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}
		if ( null !== $existing ) {
			return $this->update( $existing->uuid, $changes, $user_id );
		}

		$data = array_merge(
			array(
				'label'       => '',
				'placeholder' => '',
				'priority'    => $default->priority,
				'enabled'     => true,
			),
			$changes
		);

		$data['settings'] = array_merge(
			array(
				'required' => $default->required,
				'width'    => $default->width->value,
			),
			is_array( $changes['settings'] ?? null ) ? $changes['settings'] : array()
		);

		$saved = $this->fields->save( $this->build_core( $data, null, $section, $key, $user_id ) );
		$this->audit->record( AuditAction::Create, 'field', $saved->uuid, null, $saved->to_array(), $user_id );
		return $saved;
	}

	/**
	 * @param SectionKey|null $section Only this section; null for all.
	 */
	public function list( ?SectionKey $section = null ): FieldCollection {
		$all = $this->fields->all();
		return null === $section ? $all : $all->in_section( $section );
	}

	/**
	 * @param string $uuid Field UUID.
	 */
	public function get( string $uuid ): ?Field {
		return $this->fields->find( $uuid );
	}

	/**
	 * @param array<string, mixed> $data    Definition: section, key, type, label, placeholder, settings, priority, enabled.
	 * @param int                  $user_id Acting user.
	 * @throws InvalidFieldInput When the definition is not acceptable.
	 * @throws DatabaseError     When it cannot be stored.
	 */
	public function create( array $data, int $user_id ): Field {
		$saved = $this->fields->save( $this->build( $data, null, $user_id ) );
		$this->audit->record( AuditAction::Create, 'field', $saved->uuid, null, $saved->to_array(), $user_id );
		return $saved;
	}

	/**
	 * Partial update: only the given keys change; `settings` keys merge into the current settings.
	 *
	 * @param string               $uuid    Field UUID.
	 * @param array<string, mixed> $changes Changed values.
	 * @param int                  $user_id Acting user.
	 * @throws FieldNotFound     When the field does not exist.
	 * @throws InvalidFieldInput When the result is not acceptable.
	 * @throws DatabaseError     When it cannot be stored.
	 */
	public function update( string $uuid, array $changes, int $user_id ): Field {
		$existing = $this->fields->find( $uuid );
		if ( null === $existing ) {
			throw new FieldNotFound();
		}

		$data = array_merge( $existing->to_array(), $changes );
		if ( isset( $changes['settings'] ) && is_array( $changes['settings'] ) ) {
			$data['settings'] = array_merge( $existing->settings->to_array(), $changes['settings'] );
		}

		$saved = $this->fields->save( $this->build( $data, $existing, $user_id ) );
		$this->audit->record( AuditAction::Update, 'field', $saved->uuid, $existing->to_array(), $saved->to_array(), $user_id );
		return $saved;
	}

	/**
	 * @param string $uuid    Field UUID.
	 * @param int    $user_id Acting user.
	 * @throws FieldNotFound When the field does not exist.
	 * @throws DatabaseError When it cannot be deleted.
	 */
	public function delete( string $uuid, int $user_id ): void {
		$existing = $this->fields->find( $uuid );
		if ( null === $existing ) {
			throw new FieldNotFound();
		}
		$this->fields->delete( $uuid );
		$this->audit->record( AuditAction::Delete, 'field', $uuid, $existing->to_array(), null, $user_id );
	}

	/**
	 * Sets the order of a section's fields. The list must name each field of the section once.
	 *
	 * @param SectionKey   $section Section.
	 * @param list<string> $uuids   Field UUIDs in the new order.
	 * @param int          $user_id Acting user.
	 * @throws InvalidFieldInput When the list does not match the section's fields.
	 * @throws DatabaseError     When a field cannot be stored.
	 */
	public function reorder( SectionKey $section, array $uuids, int $user_id ): FieldCollection {
		$current  = $this->list( $section );
		$expected = array_map( static fn( Field $field ): string => $field->uuid, $current->to_list() );
		$given    = $uuids;
		sort( $expected );
		sort( $given );
		if ( $expected !== $given ) {
			throw InvalidFieldInput::with( array( 'order' => __( 'The order must list every field of the section exactly once.', 'rachnix-checkout-fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		$before = array();
		$after  = array();
		foreach ( array_values( $uuids ) as $index => $uuid ) {
			$field    = $current->find( $uuid );
			$priority = ( $index + 1 ) * 10;
			if ( null !== $field && $field->priority !== $priority ) {
				$this->fields->save( $field->with_priority( $priority ) );
				$before[ $field->key ] = $field->priority;
				$after[ $field->key ]  = $priority;
			}
		}

		$this->audit->record( AuditAction::Update, 'section', self::section_id( $section ), array( 'priority' => $before ), array( 'priority' => $after ), $user_id );
		return $this->list( $section );
	}

	/**
	 * Removes every field definition of a section, so WooCommerce's defaults apply again.
	 * Values already saved on orders are not touched.
	 *
	 * @param SectionKey $section Section.
	 * @param int        $user_id Acting user.
	 * @return int Fields removed.
	 * @throws DatabaseError When a field cannot be deleted.
	 */
	public function reset_section( SectionKey $section, int $user_id ): int {
		$fields = $this->list( $section )->to_list();
		foreach ( $fields as $field ) {
			$this->fields->delete( $field->uuid );
		}
		$this->audit->record(
			AuditAction::Reset,
			'section',
			self::section_id( $section ),
			array( 'fields' => array_map( static fn( Field $field ): array => $field->to_array(), $fields ) ),
			null,
			$user_id
		);
		return count( $fields );
	}

	/**
	 * @param array<string, mixed> $data     Merged definition.
	 * @param Field|null           $existing Current field when updating.
	 * @param int                  $user_id  Acting user.
	 * @throws InvalidFieldInput When the definition is not acceptable.
	 */
	private function build( array $data, ?Field $existing, int $user_id ): Field {
		if ( null !== $existing && $existing->is_core ) {
			// Section, key and type of a core override never change.
			return $this->build_core( $data, $existing, $existing->section, $existing->key, $user_id );
		}

		$errors = array();

		$section = $this->parse_section( $data['section'] ?? null, $errors );

		$type_slug = self::string( $data['type'] ?? '' );
		$type      = $this->types->has( $type_slug ) ? $this->types->get( $type_slug ) : null;
		if ( null === $type ) {
			$errors['type'] = __( 'Choose a field type.', 'rachnix-checkout-fields' );
		}

		$key = self::string( $data['key'] ?? '' );
		$this->check_key( $key, $existing, $errors );

		$label       = $this->text( $data['label'] ?? '', 'label', $errors );
		$placeholder = $this->text( $data['placeholder'] ?? '', 'placeholder', $errors );

		$settings = new FieldSettings();
		if ( null !== $type ) {
			$input    = is_array( $data['settings'] ?? null ) ? $data['settings'] : array();
			$settings = $this->build_settings( $input, $type, $existing, $errors );
			if ( ! $type->supports_placeholder() ) {
				$placeholder = '';
			}
		}

		$priority = $data['priority'] ?? null;
		if ( ! is_int( $priority ) ) {
			$priority = $existing->priority ?? $this->next_priority( $section );
		}

		if ( array() !== $errors ) {
			throw InvalidFieldInput::with( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		try {
			return new Field(
				uuid: $existing->uuid ?? wp_generate_uuid4(),
				section: $section ?? throw new \LogicException( 'Unreachable: section errors are reported above.' ),
				key: $key,
				type: $type_slug,
				label: $label,
				placeholder: $placeholder,
				settings: $settings,
				priority: $priority,
				enabled: is_bool( $data['enabled'] ?? null ) ? $data['enabled'] : ( $existing->enabled ?? true ),
				is_core: $existing->is_core ?? false,
				created_by: $existing->created_by ?? $user_id,
				id: $existing?->id,
				created_at: $existing?->created_at,
			);
		} catch ( InvalidFieldDefinition $e ) {
			// Only reachable if a check above is missing; report it instead of failing with a 500.
			throw InvalidFieldInput::with( array( 'field' => $e->getMessage() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}
	}

	/**
	 * A core override may change only label, placeholder, priority, enabled, and the
	 * required, width and CSS class settings. An empty label or placeholder keeps
	 * WooCommerce's own (translated) text.
	 *
	 * @param array<string, mixed> $data     Merged definition.
	 * @param Field|null           $existing Current override when updating.
	 * @param SectionKey           $section  Section.
	 * @param string               $key      WooCommerce field key.
	 * @param int                  $user_id  Acting user.
	 * @throws InvalidFieldInput When a change is not allowed.
	 */
	private function build_core( array $data, ?Field $existing, SectionKey $section, string $key, int $user_id ): Field {
		$errors      = array();
		$label       = $this->text( $data['label'] ?? '', 'label', $errors );
		$placeholder = $this->text( $data['placeholder'] ?? '', 'placeholder', $errors );
		$input       = is_array( $data['settings'] ?? null ) ? $data['settings'] : array();

		$required = true === ( $input['required'] ?? false );
		if ( ! $required && in_array( $key, self::ALWAYS_REQUIRED, true ) ) {
			$errors['settings.required'] = __( 'WooCommerce needs this field to be filled in.', 'rachnix-checkout-fields' );
		}

		$enabled = is_bool( $data['enabled'] ?? null ) ? $data['enabled'] : ( $existing->enabled ?? true );
		if ( ! $enabled && in_array( $key, self::NOT_HIDEABLE, true ) ) {
			$errors['enabled'] = __( 'The checkout cannot work without this field, so it cannot be hidden.', 'rachnix-checkout-fields' );
		}

		$width = Width::tryFrom( self::string( $input['width'] ?? Width::Full->value ) );
		if ( null === $width ) {
			$errors['settings.width'] = __( 'Width must be full or half.', 'rachnix-checkout-fields' );
		}

		$css      = $this->build_css_classes( $input['css_classes'] ?? array(), $errors );
		$priority = is_int( $data['priority'] ?? null ) ? $data['priority'] : ( $existing->priority ?? 10 );

		if ( array() !== $errors ) {
			throw InvalidFieldInput::with( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- returned as REST JSON, never printed.
		}

		return new Field(
			uuid: $existing->uuid ?? wp_generate_uuid4(),
			section: $section,
			key: $key,
			type: self::CORE_TYPE,
			label: $label,
			placeholder: $placeholder,
			settings: new FieldSettings( required: $required, width: $width ?? Width::Full, css_classes: $css ),
			priority: $priority,
			enabled: $enabled,
			is_core: true,
			created_by: $existing->created_by ?? $user_id,
			id: $existing?->id,
			created_at: $existing?->created_at,
		);
	}

	/**
	 * @param mixed                 $value  Submitted section.
	 * @param array<string, string> $errors Collected errors.
	 */
	private function parse_section( mixed $value, array &$errors ): ?SectionKey {
		try {
			$section = SectionKey::from_string( self::string( $value ) );
		} catch ( InvalidFieldDefinition ) {
			$errors['section'] = __( 'Choose a checkout section.', 'rachnix-checkout-fields' );
			return null;
		}

		if ( $section->is_custom() ) {
			/**
			 * Whether a custom section exists. Custom sections are a Pro feature; Free knows none.
			 *
			 * @param bool       $exists  Default false.
			 * @param SectionKey $section The section.
			 */
			if ( true !== apply_filters( 'rxcf_custom_section_exists', false, $section ) ) {
				$errors['section'] = __( 'This section does not exist.', 'rachnix-checkout-fields' );
				return null;
			}
		}
		return $section;
	}

	/**
	 * @param string                $key      Field key.
	 * @param Field|null            $existing Current field when updating.
	 * @param array<string, string> $errors   Collected errors.
	 */
	private function check_key( string $key, ?Field $existing, array &$errors ): void {
		if ( 1 !== preg_match( Patterns::FIELD_KEY, $key ) ) {
			$errors['key'] = __( 'Use 2 to 64 lowercase letters, digits or underscores, starting with a letter. Example: gift_message', 'rachnix-checkout-fields' );
			return;
		}
		$is_core = $existing->is_core ?? false;
		if ( ! $is_core && in_array( $key, self::RESERVED_KEYS, true ) ) {
			$errors['key'] = __( 'This key is used by WooCommerce. Choose another one.', 'rachnix-checkout-fields' );
			return;
		}
		// Unique across all sections: values are saved on the order as _rxcf_{key}.
		foreach ( $this->fields->all() as $other ) {
			if ( $other->key === $key && $other->uuid !== $existing?->uuid ) {
				$errors['key'] = __( 'Another field already uses this key.', 'rachnix-checkout-fields' );
				return;
			}
		}
	}

	/**
	 * @param array<mixed>          $input    Submitted settings.
	 * @param FieldTypeInterface    $type     Field type.
	 * @param Field|null            $existing Current field when updating.
	 * @param array<string, string> $errors   Collected errors.
	 */
	private function build_settings( array $input, FieldTypeInterface $type, ?Field $existing, array &$errors ): FieldSettings {
		$options = $type->supports_options() ? $this->build_options( $input['options'] ?? array(), $errors ) : array();

		$default = $type->sanitize( $input['default'] ?? '' );
		if ( mb_strlen( $default ) > $type->max_length() ) {
			$errors['settings.default'] = __( 'The default value is too long.', 'rachnix-checkout-fields' );
		} elseif ( $type->supports_options() && '' !== $default && ! in_array( $default, array_map( static fn( FieldOption $o ): string => $o->value, $options ), true ) ) {
			$errors['settings.default'] = __( 'The default value must be one of the choices.', 'rachnix-checkout-fields' );
		}

		$width = Width::Full;
		if ( isset( $input['width'] ) ) {
			$width = Width::tryFrom( self::string( $input['width'] ) ) ?? Width::Full;
			if ( self::string( $input['width'] ) !== $width->value ) {
				$errors['settings.width'] = __( 'Width must be full or half.', 'rachnix-checkout-fields' );
			}
		}

		$display = DisplayFlags::from_array( is_array( $input['display'] ?? null ) ? $input['display'] : array() );
		if ( $display->order_list && $this->order_list_fields_besides( $existing ) >= self::MAX_ORDER_LIST_FIELDS ) {
			/* translators: %d: maximum number of fields. */
			$errors['settings.display.order_list'] = sprintf( __( 'At most %d fields can be shown in the orders list.', 'rachnix-checkout-fields' ), self::MAX_ORDER_LIST_FIELDS );
		}

		/**
		 * Lets an add-on (Pro) validate and keep its own settings, e.g. conditions.
		 * Free ignores submitted extensions and keeps the stored ones.
		 *
		 * @param array<mixed> $extensions Stored extensions.
		 * @param mixed        $submitted  Submitted `settings.extensions`, unvalidated.
		 * @param string       $type       Field type slug.
		 */
		$extensions = apply_filters( 'rxcf_field_extensions', $existing?->settings->extensions ?? array(), $input['extensions'] ?? null, $type->slug() );
		$required   = $input['required'] ?? false;

		return new FieldSettings(
			default_value: $default,
			required: true === $required && $type->can_be_required(),
			width: $width,
			css_classes: $this->build_css_classes( $input['css_classes'] ?? array(), $errors ),
			options: $options,
			validation: $this->build_rules( $input['validation'] ?? array(), $type, $errors ),
			display: $display,
			extensions: is_array( $extensions ) ? $extensions : array(),
		);
	}

	/**
	 * @param mixed                 $raw    Submitted options.
	 * @param array<string, string> $errors Collected errors.
	 * @return list<FieldOption>
	 */
	private function build_options( mixed $raw, array &$errors ): array {
		if ( ! is_array( $raw ) || count( $raw ) > self::MAX_OPTIONS ) {
			/* translators: %d: maximum number of choices. */
			$errors['settings.options'] = sprintf( __( 'Add between 1 and %d choices.', 'rachnix-checkout-fields' ), self::MAX_OPTIONS );
			return array();
		}

		$options = array();
		$seen    = array();
		foreach ( array_values( $raw ) as $index => $entry ) {
			$value = sanitize_text_field( self::string( is_array( $entry ) ? ( $entry['value'] ?? '' ) : '' ) );
			$label = sanitize_text_field( self::string( is_array( $entry ) ? ( $entry['label'] ?? $value ) : '' ) );
			$path  = "settings.options.{$index}";

			if ( '' === $value || mb_strlen( $value ) > 255 || mb_strlen( $label ) > 255 ) {
				$errors[ $path ] = __( 'Each choice needs a value of up to 255 characters.', 'rachnix-checkout-fields' );
			} elseif ( isset( $seen[ $value ] ) ) {
				$errors[ $path ] = __( 'Two choices have the same value.', 'rachnix-checkout-fields' );
			} else {
				$seen[ $value ] = true;
				$options[]      = new FieldOption( $value, '' === $label ? $value : $label );
			}
		}

		if ( array() === $raw ) {
			$errors['settings.options'] = __( 'Add at least one choice.', 'rachnix-checkout-fields' );
		}
		return $options;
	}

	/**
	 * @param mixed                 $raw    Submitted classes.
	 * @param array<string, string> $errors Collected errors.
	 * @return list<string>
	 */
	private function build_css_classes( mixed $raw, array &$errors ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$classes = array();
		foreach ( $raw as $class ) {
			if ( ! is_string( $class ) || 1 !== preg_match( Patterns::CSS_CLASS, $class ) ) {
				$errors['settings.css_classes'] = __( 'CSS classes may contain only letters, digits, dashes and underscores.', 'rachnix-checkout-fields' );
				return array();
			}
			$classes[] = $class;
		}
		if ( count( $classes ) > self::MAX_CSS_CLASSES ) {
			/* translators: %d: maximum number of classes. */
			$errors['settings.css_classes'] = sprintf( __( 'Use at most %d CSS classes.', 'rachnix-checkout-fields' ), self::MAX_CSS_CLASSES );
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * @param mixed                 $raw    Submitted rules.
	 * @param FieldTypeInterface    $type   Field type.
	 * @param array<string, string> $errors Collected errors.
	 * @return list<ValidationRule>
	 */
	private function build_rules( mixed $raw, FieldTypeInterface $type, array &$errors ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		if ( count( $raw ) > self::MAX_RULES ) {
			/* translators: %d: maximum number of rules. */
			$errors['settings.validation'] = sprintf( __( 'Use at most %d rules.', 'rachnix-checkout-fields' ), self::MAX_RULES );
			return array();
		}

		$rules = array();
		foreach ( array_values( $raw ) as $index => $entry ) {
			$path = "settings.validation.{$index}";
			$rule = ValidationRule::from_array( $entry );

			if ( null === $rule ) {
				$errors[ $path ] = __( 'Unknown validation rule.', 'rachnix-checkout-fields' );
			} elseif ( ! in_array( $rule->type, $type->allowed_rules(), true ) ) {
				$errors[ $path ] = __( 'This rule is not available for this field type.', 'rachnix-checkout-fields' );
			} elseif ( ValidationRuleType::Pattern === $rule->type && ( null === $rule->pattern_id || null === $this->patterns->regex( $rule->pattern_id ) ) ) {
				$errors[ $path ] = __( 'Choose a format from the list.', 'rachnix-checkout-fields' );
			} elseif ( null !== $rule->min && null !== $rule->max && $rule->min > $rule->max ) {
				$errors[ $path ] = __( 'The minimum is larger than the maximum.', 'rachnix-checkout-fields' );
			} elseif ( ValidationRuleType::Length === $rule->type && ( ( $rule->min ?? 0 ) < 0 || ( $rule->max ?? 0 ) > $type->max_length() || ( null === $rule->min && null === $rule->max ) ) ) {
				/* translators: %d: maximum length of this field type. */
				$errors[ $path ] = sprintf( __( 'Set a length between 0 and %d characters.', 'rachnix-checkout-fields' ), $type->max_length() );
			} elseif ( ValidationRuleType::NumberRange === $rule->type && null === $rule->min && null === $rule->max ) {
				$errors[ $path ] = __( 'Set a minimum, a maximum or both.', 'rachnix-checkout-fields' );
			} else {
				$rules[] = $rule;
			}
		}
		return $rules;
	}

	/**
	 * @param mixed                 $raw    Submitted text.
	 * @param string                $param  Parameter name for errors.
	 * @param array<string, string> $errors Collected errors.
	 */
	private function text( mixed $raw, string $param, array &$errors ): string {
		$text = sanitize_text_field( self::string( $raw ) );
		if ( mb_strlen( $text ) > Field::MAX_TEXT_LENGTH ) {
			/* translators: %d: maximum number of characters. */
			$errors[ $param ] = sprintf( __( 'Use at most %d characters.', 'rachnix-checkout-fields' ), Field::MAX_TEXT_LENGTH );
		}
		return $text;
	}

	/**
	 * @param Field|null $existing Field being updated, which does not count against the limit.
	 */
	private function order_list_fields_besides( ?Field $existing ): int {
		$count = 0;
		foreach ( $this->fields->all() as $field ) {
			if ( $field->settings->display->order_list && $field->uuid !== $existing?->uuid ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param SectionKey|null $section Section.
	 */
	private function next_priority( ?SectionKey $section ): int {
		if ( null === $section ) {
			return 10;
		}
		$highest = 0;
		foreach ( $this->list( $section ) as $field ) {
			$highest = max( $highest, $field->priority );
		}
		return $highest + 10;
	}

	/**
	 * Audit log ids are at most 36 characters: the section slug, or a custom section's UUID.
	 *
	 * @param SectionKey $section Section.
	 */
	private static function section_id( SectionKey $section ): string {
		return $section->is_custom() ? substr( $section->value, strlen( 'custom:' ) ) : $section->value;
	}

	/**
	 * @param mixed $value Submitted value.
	 */
	private static function string( mixed $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? (string) $value : '';
	}
}
