<?php
/**
 * A checkout field definition.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

use DateTimeImmutable;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * One row of `rxcf_fields`: a WooCommerce core field we override, or a custom field.
 * Whether `$type` exists in the FieldTypeRegistry is checked by the service layer.
 */
final readonly class Field {

	public const MAX_TEXT_LENGTH = 255;

	/**
	 * @param string                 $uuid        Stable id, used for import/export and block field ids.
	 * @param SectionKey             $section     Section the field belongs to.
	 * @param string                 $key         Field key, unique within its section.
	 * @param string                 $type        Field type slug.
	 * @param string                 $label       Label shown to the customer.
	 * @param string                 $placeholder Placeholder text.
	 * @param FieldSettings          $settings    Everything else.
	 * @param int                    $priority    Sort order; lower comes first.
	 * @param bool                   $enabled     False hides the field from the checkout.
	 * @param bool                   $is_core     True for a WooCommerce default field we override.
	 * @param int                    $created_by  User id of the creator.
	 * @param int|null               $id          Database id; null until saved.
	 * @param DateTimeImmutable|null $created_at  UTC; null until saved.
	 * @param DateTimeImmutable|null $updated_at  UTC; null until saved.
	 * @throws InvalidFieldDefinition When an identifier or text is malformed.
	 */
	public function __construct(
		public string $uuid,
		public SectionKey $section,
		public string $key,
		public string $type,
		public string $label = '',
		public string $placeholder = '',
		public FieldSettings $settings = new FieldSettings(),
		public int $priority = 10,
		public bool $enabled = true,
		public bool $is_core = false,
		public int $created_by = 0,
		public ?int $id = null,
		public ?DateTimeImmutable $created_at = null,
		public ?DateTimeImmutable $updated_at = null,
	) {
		if ( 1 !== preg_match( Patterns::UUID, $uuid ) ) {
			throw InvalidFieldDefinition::invalid( 'uuid' );
		}
		if ( 1 !== preg_match( Patterns::FIELD_KEY, $key ) ) {
			throw InvalidFieldDefinition::invalid( 'key' );
		}
		if ( 1 !== preg_match( Patterns::TYPE_SLUG, $type ) ) {
			throw InvalidFieldDefinition::invalid( 'type' );
		}
		// Messages name the property and a constant only, never the rejected text.
		if ( mb_strlen( $label ) > self::MAX_TEXT_LENGTH ) {
			throw InvalidFieldDefinition::too_long( 'label', self::MAX_TEXT_LENGTH ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( mb_strlen( $placeholder ) > self::MAX_TEXT_LENGTH ) {
			throw InvalidFieldDefinition::too_long( 'placeholder', self::MAX_TEXT_LENGTH ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( null !== $id && $id < 1 ) {
			throw InvalidFieldDefinition::invalid( 'id' );
		}
	}

	/**
	 * The definition as plain data, for the REST API, the audit log and export.
	 * No database id, user id or timestamps.
	 *
	 * @return array{uuid: string, section: string, key: string, type: string, label: string, placeholder: string, settings: array<string, mixed>, priority: int, enabled: bool, is_core: bool}
	 */
	public function to_array(): array {
		return array(
			'uuid'        => $this->uuid,
			'section'     => $this->section->value,
			'key'         => $this->key,
			'type'        => $this->type,
			'label'       => $this->label,
			'placeholder' => $this->placeholder,
			'settings'    => $this->settings->to_array(),
			'priority'    => $this->priority,
			'enabled'     => $this->enabled,
			'is_core'     => $this->is_core,
		);
	}

	/**
	 * Copy with a new sort position.
	 *
	 * @param int $priority Sort order.
	 */
	public function with_priority( int $priority ): self {
		return new self(
			uuid: $this->uuid,
			section: $this->section,
			key: $this->key,
			type: $this->type,
			label: $this->label,
			placeholder: $this->placeholder,
			settings: $this->settings,
			priority: $priority,
			enabled: $this->enabled,
			is_core: $this->is_core,
			created_by: $this->created_by,
			id: $this->id,
			created_at: $this->created_at,
			updated_at: $this->updated_at,
		);
	}

	/**
	 * Copy of this field as stored in the database.
	 *
	 * @param int               $id         Database id.
	 * @param DateTimeImmutable $created_at UTC.
	 * @param DateTimeImmutable $updated_at UTC.
	 */
	public function with_persistence( int $id, DateTimeImmutable $created_at, DateTimeImmutable $updated_at ): self {
		return new self(
			uuid: $this->uuid,
			section: $this->section,
			key: $this->key,
			type: $this->type,
			label: $this->label,
			placeholder: $this->placeholder,
			settings: $this->settings,
			priority: $this->priority,
			enabled: $this->enabled,
			is_core: $this->is_core,
			created_by: $this->created_by,
			id: $id,
			created_at: $created_at,
			updated_at: $updated_at,
		);
	}
}
