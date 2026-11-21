<?php
/**
 * REST routes for publishing and previewing a draft.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use Rachnix\CheckoutFields\Service\PreviewService;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * POST /publish applies the admin's draft (a list of operations) in one transaction.
 * POST /preview validates the same list and returns a URL that shows the checkout with it.
 */
final class PublishController extends AbstractController {

	public const MAX_OPERATIONS = 500;

	/**
	 * @param FieldService   $service         Field changes.
	 * @param PreviewService $previews        Draft previews.
	 * @param Logger         $logger          Error log.
	 * @param string         $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly FieldService $service,
		private readonly PreviewService $previews,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'publish';
	}

	public function register_routes(): void {
		$args = array(
			'operations' => array(
				'type'     => 'array',
				'required' => true,
				'maxItems' => self::MAX_OPERATIONS,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						'op' => array(
							'type'     => 'string',
							'enum'     => array( 'create', 'update', 'delete', 'core', 'reorder' ),
							'required' => true,
						),
					),
				),
			),
		);

		register_rest_route(
			$this->route_namespace(),
			'/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			$this->route_namespace(),
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function publish( \WP_REST_Request $request ) {
		try {
			$this->service->apply_batch( $this->operations( $request ), get_current_user_id() );
		} catch ( InvalidFieldInput | DatabaseError $e ) {
			return $this->error_response( $e );
		}

		return new \WP_REST_Response(
			array( 'fields' => array_map( fn( Field $field ): array => $this->field_data( $field ), $this->service->list()->to_list() ) )
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( \WP_REST_Request $request ) {
		try {
			$url = $this->previews->start( $this->operations( $request ), get_current_user_id() );
		} catch ( InvalidFieldInput | DatabaseError $e ) {
			return $this->error_response( $e );
		}
		return new \WP_REST_Response( array( 'url' => $url ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return list<mixed>
	 */
	private function operations( \WP_REST_Request $request ): array {
		$operations = $request->get_param( 'operations' );
		return is_array( $operations ) ? array_values( $operations ) : array();
	}
}
