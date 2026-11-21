<?php
/**
 * REST route for the system info shown on the Tools tab.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\SystemInfo;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /system-info: versions and setup for bug reports, as groups and as plain text.
 */
final class SystemInfoController extends AbstractController {

	/**
	 * @param SystemInfo $info            System info.
	 * @param Logger     $logger          Error log.
	 * @param string     $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly SystemInfo $info,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'system-info';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->route_namespace(),
			'/system-info',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_info' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function get_info(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'groups' => $this->info->collect(),
				'text'   => $this->info->as_text(),
			)
		);
	}
}
