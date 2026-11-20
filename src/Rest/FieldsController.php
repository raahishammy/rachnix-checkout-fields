<?php
/**
 * REST routes for field definitions.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\Domain\Width;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\FieldNotFound;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /fields, GET/PATCH/DELETE /fields/{uuid}, POST /fields/reorder, GET /field-types.
 */
final class FieldsController extends AbstractController {

	/** Parameters a client may send for a field; anything else is ignored. */
	private const WRITABLE = array( 'section', 'key', 'type', 'label', 'placeholder', 'settings', 'priority', 'enabled' );

	/**
	 * @param FieldService      $service   Field changes.
	 * @param FieldTypeRegistry $types     Field types.
	 * @param PatternLibrary    $patterns  Curated patterns.
	 * @param Logger            $logger    Error log.
	 * @param string            $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly FieldService $service,
		private readonly FieldTypeRegistry $types,
		private readonly PatternLibrary $patterns,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'fields';
	}

	public function register_routes(): void {
		$permission = array( $this, 'can_manage' );

		register_rest_route(
			$this->route_namespace(),
			'/fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $permission,
					'args'                => array(
						'section' => array(
							'type'    => 'string',
							'pattern' => self::SECTION_PATTERN,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $permission,
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->route_namespace(),
			'/fields/' . self::UUID_PARAM,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $permission,
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $permission,
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->route_namespace(),
			'/fields/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => $permission,
				'args'                => array(
					'section' => array(
						'type'     => 'string',
						'pattern'  => self::SECTION_PATTERN,
						'required' => true,
					),
					'order'   => array(
						'type'        => 'array',
						'required'    => true,
						'maxItems'    => 500,
						'uniqueItems' => true,
						'items'       => array(
							'type'   => 'string',
							'format' => 'uuid',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->route_namespace(),
			'/field-types',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_types' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$section = null;
		if ( is_string( $request->get_param( 'section' ) ) ) {
			try {
				$section = SectionKey::from_string( $request->get_param( 'section' ) );
			} catch ( InvalidFieldDefinition ) {
				return new \WP_Error( 'rest_invalid_param', __( 'Unknown section.', 'rachnix-checkout-fields' ), array( 'status' => 400 ) );
			}
		}

		$items = array();
		foreach ( $this->service->list( $section ) as $field ) {
			$items[] = $this->data( $field );
		}
		return rest_ensure_response( $items );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$field = $this->service->get( (string) $request->get_param( 'uuid' ) );
		return null === $field ? $this->error_response( new FieldNotFound() ) : rest_ensure_response( $this->data( $field ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		try {
			$field = $this->service->create( $this->input( $request ), get_current_user_id() );
		} catch ( InvalidFieldInput | DatabaseError $e ) {
			return $this->error_response( $e );
		}

		$response = rest_ensure_response( $this->data( $field ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/fields/%s', $this->namespace, $field->uuid ) ) );
		return $response;
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		try {
			$field = $this->service->update( (string) $request->get_param( 'uuid' ), $this->input( $request ), get_current_user_id() );
		} catch ( InvalidFieldInput | FieldNotFound | DatabaseError $e ) {
			return $this->error_response( $e );
		}
		return rest_ensure_response( $this->data( $field ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$uuid  = (string) $request->get_param( 'uuid' );
		$field = $this->service->get( $uuid );
		try {
			if ( null === $field ) {
				throw new FieldNotFound();
			}
			$this->service->delete( $uuid, get_current_user_id() );
		} catch ( FieldNotFound | DatabaseError $e ) {
			return $this->error_response( $e );
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $this->data( $field ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reorder( \WP_REST_Request $request ) {
		$order = $request->get_param( 'order' );
		try {
			$section = SectionKey::from_string( (string) $request->get_param( 'section' ) );
			$fields  = $this->service->reorder( $section, is_array( $order ) ? array_values( array_map( 'strval', $order ) ) : array(), get_current_user_id() );
		} catch ( InvalidFieldDefinition ) {
			return new \WP_Error( 'rest_invalid_param', __( 'Unknown section.', 'rachnix-checkout-fields' ), array( 'status' => 400 ) );
		} catch ( InvalidFieldInput | DatabaseError $e ) {
			return $this->error_response( $e );
		}
		return rest_ensure_response( array_map( fn( Field $field ): array => $this->data( $field ), $fields->to_list() ) );
	}

	/**
	 * Field types and curated patterns, for the admin's type picker and rule editor.
	 */
	public function get_types(): \WP_REST_Response {
		$types = array_map(
			static fn( FieldTypeInterface $type ): array => array(
				'slug'                 => $type->slug(),
				'label'                => $type->label(),
				'description'          => $type->description(),
				'icon'                 => $type->icon(),
				'supports_options'     => $type->supports_options(),
				'supports_placeholder' => $type->supports_placeholder(),
				'can_be_required'      => $type->can_be_required(),
				'allowed_rules'        => array_map( static fn( ValidationRuleType $rule ): string => $rule->value, $type->allowed_rules() ),
				'max_length'           => $type->max_length(),
			),
			$this->types->all()
		);

		$patterns = array_map(
			fn( string $id ): array => array(
				'id'      => $id,
				'label'   => $this->patterns->label( $id ),
				'example' => $this->patterns->example( $id ),
			),
			$this->patterns->ids()
		);

		return new \WP_REST_Response(
			array(
				'types'    => $types,
				'patterns' => $patterns,
			)
		);
	}

	/**
	 * JSON schema of a field. WordPress validates request bodies against it before
	 * the callback runs; FieldService then checks the business rules.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$text = static fn( int $max ): array => array(
			'type'      => 'string',
			'maxLength' => $max,
		);
		$flag = array( 'type' => 'boolean' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'rxcf_field',
			'type'       => 'object',
			'properties' => array(
				'uuid'        => array(
					'type'     => 'string',
					'format'   => 'uuid',
					'readonly' => true,
				),
				'section'     => array(
					'type'     => 'string',
					'pattern'  => self::SECTION_PATTERN,
					'required' => true,
				),
				'key'         => array(
					'type'     => 'string',
					'pattern'  => '^[a-z][a-z0-9_]{1,63}$',
					'required' => true,
				),
				'type'        => array(
					'type'     => 'string',
					'enum'     => $this->types->type_slugs(),
					'required' => true,
				),
				'label'       => $text( 255 ),
				'placeholder' => $text( 255 ),
				'priority'    => array(
					'type'    => 'integer',
					'minimum' => -100000,
					'maximum' => 100000,
				),
				'enabled'     => $flag,
				'is_core'     => array(
					'type'     => 'boolean',
					'readonly' => true,
				),
				'settings'    => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'default'     => $text( 5000 ),
						'required'    => $flag,
						'width'       => array(
							'type' => 'string',
							'enum' => array_map( static fn( Width $w ): string => $w->value, Width::cases() ),
						),
						'css_classes' => array(
							'type'     => 'array',
							'maxItems' => FieldService::MAX_CSS_CLASSES,
							'items'    => array(
								'type'    => 'string',
								'pattern' => '^[A-Za-z_-][A-Za-z0-9_-]{0,63}$',
							),
						),
						'options'     => array(
							'type'     => 'array',
							'maxItems' => FieldService::MAX_OPTIONS,
							'items'    => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'value' => array(
										'type'      => 'string',
										'minLength' => 1,
										'maxLength' => 255,
										'required'  => true,
									),
									'label' => $text( 255 ),
								),
							),
						),
						'validation'  => array(
							'type'     => 'array',
							'maxItems' => FieldService::MAX_RULES,
							'items'    => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'rule'    => array(
										'type'     => 'string',
										'enum'     => array_map( static fn( ValidationRuleType $r ): string => $r->value, ValidationRuleType::cases() ),
										'required' => true,
									),
									'min'     => array( 'type' => 'number' ),
									'max'     => array( 'type' => 'number' ),
									'pattern' => array(
										'type' => 'string',
										'enum' => $this->patterns->ids(),
									),
								),
							),
						),
						'display'     => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'order_admin'    => $flag,
								'my_account'     => $flag,
								'thank_you'      => $flag,
								'admin_email'    => $flag,
								'customer_email' => $flag,
								'order_list'     => $flag,
							),
						),
						'extensions'  => array( 'type' => 'object' ),
					),
				),
				'created_at'  => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'readonly' => true,
				),
				'updated_at'  => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'readonly' => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * @param Field $field Field.
	 * @return array<string, mixed>
	 */
	private function data( Field $field ): array {
		return $field->to_array() + array(
			'created_at' => $field->created_at?->format( DATE_ATOM ),
			'updated_at' => $field->updated_at?->format( DATE_ATOM ),
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return array<string, mixed>
	 */
	private function input( \WP_REST_Request $request ): array {
		$data = array();
		foreach ( self::WRITABLE as $name ) {
			$value = $request->get_param( $name );
			if ( null !== $value ) {
				$data[ $name ] = $value;
			}
		}
		return $data;
	}
}
