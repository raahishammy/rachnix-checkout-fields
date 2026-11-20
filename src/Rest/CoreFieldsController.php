<?php
/**
 * REST routes for WooCommerce's own checkout fields.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\Width;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\FieldNotFound;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /core-fields lists WooCommerce's fields with their overrides;
 * POST /core-fields/{section}/{key} edits, hides or moves one.
 * An override is removed (WooCommerce's field restored) with DELETE /fields/{uuid}.
 */
final class CoreFieldsController extends AbstractController {

	private const SECTIONS = 'billing|shipping|order';

	/**
	 * @param FieldService $service         Field changes.
	 * @param Logger       $logger          Error log.
	 * @param string       $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly FieldService $service,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'core-fields';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->route_namespace(),
			'/core-fields',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$text = array(
			'type'      => 'string',
			'maxLength' => 255,
		);

		register_rest_route(
			$this->route_namespace(),
			'/core-fields/(?P<section>' . self::SECTIONS . ')/(?P<key>[a-z][a-z0-9_]{1,63})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'customize' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'label'       => $text,
					'placeholder' => $text,
					'priority'    => array(
						'type'    => 'integer',
						'minimum' => -100000,
						'maximum' => 100000,
					),
					'enabled'     => array( 'type' => 'boolean' ),
					'settings'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'required'    => array( 'type' => 'boolean' ),
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
						),
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$overrides = $this->service->list();

		$items = array();
		foreach ( $this->service->core_defaults() as $default ) {
			$override = $overrides->find_by_key( $default->section, $default->key );
			$items[]  = array(
				'section'  => $default->section->value,
				'key'      => $default->key,
				'label'    => $default->label,
				'required' => $default->required,
				'priority' => $default->priority,
				'width'    => $default->width->value,
				'override' => null !== $override && $override->is_core ? $this->field_data( $override ) : null,
			);
		}

		return new \WP_REST_Response( $items );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function customize( \WP_REST_Request $request ) {
		$changes = array();
		foreach ( array( 'label', 'placeholder', 'priority', 'enabled', 'settings' ) as $name ) {
			if ( null !== $request->get_param( $name ) ) {
				$changes[ $name ] = $request->get_param( $name );
			}
		}

		try {
			$field = $this->service->customize_core(
				SectionKey::from_string( (string) $request->get_param( 'section' ) ),
				(string) $request->get_param( 'key' ),
				$changes,
				get_current_user_id()
			);
		} catch ( InvalidFieldInput | FieldNotFound | DatabaseError $e ) {
			return $this->error_response( $e );
		}

		return new \WP_REST_Response( $this->field_data( $field ) );
	}
}
