<?php
/**
 * REST routes for export, import, reset and the setup wizard.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\ImportExportService;
use Rachnix\CheckoutFields\Service\SettingsService;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /export, POST /import (preview or apply), POST /reset-all, POST /wizard/complete.
 */
final class ToolsController extends AbstractController {

	/**
	 * @param ImportExportService $transfer        Export and import.
	 * @param FieldService        $fields          Field changes.
	 * @param SettingsService     $settings        Settings changes.
	 * @param Logger              $logger          Error log.
	 * @param string              $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly ImportExportService $transfer,
		private readonly FieldService $fields,
		private readonly SettingsService $settings,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'tools';
	}

	public function register_routes(): void {
		$permission = array( $this, 'can_manage' );
		$confirm    = array(
			'type'     => 'boolean',
			'required' => true,
		);

		register_rest_route(
			$this->route_namespace(),
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			$this->route_namespace(),
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $permission,
				'args'                => array(
					'file'             => array(
						'type'     => 'object',
						'required' => true,
					),
					'apply'            => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'confirm_unsigned' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			$this->route_namespace(),
			'/reset-all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_all' ),
				'permission_callback' => $permission,
				'args'                => array( 'confirm' => $confirm ),
			)
		);
		register_rest_route(
			$this->route_namespace(),
			'/uninstall',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_uninstall' ),
				'permission_callback' => $permission,
				'args'                => array(
					'delete_data' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			$this->route_namespace(),
			'/wizard/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_wizard' ),
				'permission_callback' => $permission,
			)
		);
	}

	public function export(): \WP_REST_Response {
		$response = new \WP_REST_Response( $this->transfer->export() );
		$response->header( 'Content-Disposition', 'attachment; filename="rachnix-checkout-fields-' . gmdate( 'Y-m-d' ) . '.json"' );
		return $response;
	}

	/**
	 * Preview (default) shows what would change; apply imports.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( \WP_REST_Request $request ) {
		if ( strlen( $request->get_body() ) > ImportExportService::MAX_BYTES ) {
			return new \WP_Error( 'rxcf_file_too_large', __( 'The file is larger than 1 MB.', 'rachnix-checkout-fields' ), array( 'status' => 413 ) );
		}
		$file = $request->get_param( 'file' );
		$file = is_array( $file ) ? $file : array();

		try {
			$result = true === $request->get_param( 'apply' )
				? $this->transfer->import( $file, get_current_user_id(), current_user_can( 'edit_css' ), true === $request->get_param( 'confirm_unsigned' ) )
				: $this->transfer->preview( $file, get_current_user_id() );
		} catch ( InvalidFieldInput | DatabaseError $e ) {
			return $this->error_response( $e );
		}
		return new \WP_REST_Response( $result );
	}

	/**
	 * Removes every field definition and restores the default style. Order data stays.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reset_all( \WP_REST_Request $request ) {
		if ( true !== $request->get_param( 'confirm' ) ) {
			return new \WP_Error( 'rxcf_confirm_required', __( 'Send confirm: true to reset everything.', 'rachnix-checkout-fields' ), array( 'status' => 400 ) );
		}
		$deleted = 0;
		try {
			foreach ( Section::cases() as $section ) {
				$deleted += $this->fields->reset_section( SectionKey::core( $section ), get_current_user_id() );
			}
		} catch ( DatabaseError $e ) {
			return $this->error_response( $e );
		}
		$this->settings->reset_style( get_current_user_id() );
		return new \WP_REST_Response( array( 'deleted' => $deleted ) );
	}

	/**
	 * Turns "delete all data when the plugin is deleted" on or off. Only users who may
	 * delete plugins can change it, because it decides what deleting the plugin removes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_uninstall( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return new \WP_Error( 'rxcf_forbidden', __( 'Only users who can delete plugins can change this setting.', 'rachnix-checkout-fields' ), array( 'status' => 403 ) );
		}
		$this->settings->update_delete_data_on_uninstall( true === $request->get_param( 'delete_data' ), get_current_user_id() );
		return new \WP_REST_Response( array( 'delete_data' => $this->settings->delete_data_on_uninstall() ) );
	}

	public function complete_wizard(): \WP_REST_Response {
		$this->settings->complete_wizard();
		return new \WP_REST_Response( array( 'completed' => true ) );
	}
}
