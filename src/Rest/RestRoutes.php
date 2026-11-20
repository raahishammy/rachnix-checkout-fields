<?php
/**
 * Registers the REST controllers.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Closure;
use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the controllers only when the REST API starts, so normal page loads
 * (including the checkout) never construct them or their services.
 */
final class RestRoutes implements Hookable {

	/**
	 * @param Closure(): list<AbstractController> $controllers Builds the controllers.
	 */
	public function __construct(
		private readonly Closure $controllers,
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		foreach ( ( $this->controllers )() as $controller ) {
			$controller->register_routes();
		}
	}
}
