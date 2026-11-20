<?php
/**
 * Shared behaviour of the plugin's REST controllers.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\FieldNotFound;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;
use Rachnix\CheckoutFields\Security\Capabilities;
use Rachnix\CheckoutFields\Security\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Every route uses `can_manage()` as its permission callback. Cookie-authenticated
 * requests also need the `wp_rest` nonce (X-WP-Nonce); without it WordPress treats the
 * request as logged out, which is the CSRF protection.
 */
abstract class AbstractController extends \WP_REST_Controller {

	public const NAMESPACE = 'rxcf/v1';

	/** Route parameter for a field UUID. */
	protected const UUID_PARAM = '(?P<uuid>[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})';

	/** Values a section parameter may take (core slugs, or custom:{uuid}). */
	protected const SECTION_PATTERN = '^(billing|shipping|order|contact|custom:[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$';

	/**
	 * @param Logger $logger          Error log.
	 * @param string $route_namespace Route namespace; tests use their own.
	 * @throws \InvalidArgumentException When the namespace is not "vendor/v1" shaped.
	 */
	public function __construct(
		protected readonly Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		if ( 1 !== preg_match( '#^[a-z0-9-]+/v[0-9]+$#', $route_namespace ) ) {
			throw new \InvalidArgumentException( 'Invalid REST namespace.' );
		}
		$this->namespace = $route_namespace;
	}

	/**
	 * The route namespace, for register_rest_route().
	 *
	 * @return non-falsy-string
	 */
	protected function route_namespace(): string {
		$route_namespace = $this->namespace;
		if ( '' === $route_namespace || '0' === $route_namespace ) {
			throw new \LogicException( 'REST namespace is empty.' );
		}
		return $route_namespace;
	}

	/**
	 * Permission callback for every route.
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE_FIELDS );
	}

	/**
	 * Turns a service exception into an API error. Unexpected database errors are
	 * logged; the response never contains database details.
	 *
	 * @param InvalidFieldInput|FieldNotFound|DatabaseError $error Exception.
	 */
	protected function error_response( InvalidFieldInput|FieldNotFound|DatabaseError $error ): \WP_Error {
		if ( $error instanceof InvalidFieldInput ) {
			return new \WP_Error(
				'rxcf_invalid_field',
				$error->getMessage(),
				array(
					'status' => 400,
					'params' => $error->errors(),
				)
			);
		}
		if ( $error instanceof FieldNotFound ) {
			return new \WP_Error( 'rxcf_not_found', __( 'Field not found.', 'rachnix-checkout-fields' ), array( 'status' => 404 ) );
		}

		$this->logger->error( 'REST request failed.', array( 'reason' => $error->getMessage() ) );
		return new \WP_Error(
			'rxcf_server_error',
			__( 'The change could not be saved. Details are in WooCommerce > Status > Logs.', 'rachnix-checkout-fields' ),
			array( 'status' => 500 )
		);
	}
}
