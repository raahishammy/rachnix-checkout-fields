<?php
/**
 * REST routes for plugin settings.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Domain\LabelPosition;
use Rachnix\CheckoutFields\Domain\StyleMode;
use Rachnix\CheckoutFields\Domain\StyleSettings;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\SettingsService;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /settings and POST /settings (partial). Custom CSS additionally needs `edit_css`.
 */
final class SettingsController extends AbstractController {

	/**
	 * @param SettingsService $service         Settings changes.
	 * @param Logger          $logger          Error log.
	 * @param string          $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly SettingsService $service,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'settings';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->route_namespace(),
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'style' => array(
							'type'                 => 'object',
							'required'             => true,
							'additionalProperties' => false,
							'properties'           => array(
								'mode'           => array(
									'type' => 'string',
									'enum' => array_map( static fn( StyleMode $m ): string => $m->value, StyleMode::cases() ),
								),
								'accent'         => array(
									'type'      => array( 'string', 'null' ),
									'maxLength' => 7,
								),
								'radius'         => array(
									'type'    => 'integer',
									'minimum' => 0,
									'maximum' => StyleSettings::MAX_RADIUS,
								),
								'spacing'        => array(
									'type'    => 'integer',
									'minimum' => 0,
									'maximum' => StyleSettings::MAX_SPACING,
								),
								'label_position' => array(
									'type' => 'string',
									'enum' => array_map( static fn( LabelPosition $p ): string => $p->value, LabelPosition::cases() ),
								),
								'custom_css'     => array(
									'type'      => 'string',
									'maxLength' => StyleSettings::MAX_CSS_BYTES,
								),
							),
						),
					),
				),
			)
		);
	}

	public function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( $this->data() );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$style = $request->get_param( 'style' );
		try {
			$this->service->update_style( is_array( $style ) ? $style : array(), get_current_user_id(), current_user_can( 'edit_css' ) );
		} catch ( InvalidFieldInput $e ) {
			return $this->error_response( $e );
		}
		return new \WP_REST_Response( $this->data() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function data(): array {
		return array(
			'style'        => $this->service->style()->to_array(),
			'can_edit_css' => current_user_can( 'edit_css' ),
		);
	}
}
