<?php
/**
 * REST routes for checkout sections.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Rest;

use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\InvalidFieldDefinition;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\FieldService;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /sections, POST /sections/{section}/reset. Custom sections (Pro) are added
 * to the list through the `rxcf_rest_sections` filter.
 */
final class SectionsController extends AbstractController {

	/**
	 * @param FieldService $service   Field changes.
	 * @param Logger       $logger    Error log.
	 * @param string       $route_namespace Route namespace.
	 */
	public function __construct(
		private readonly FieldService $service,
		Logger $logger,
		string $route_namespace = self::NAMESPACE,
	) {
		parent::__construct( $logger, $route_namespace );
		$this->rest_base = 'sections';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->route_namespace(),
			'/sections',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$this->route_namespace(),
			'/sections/(?P<section>' . trim( self::SECTION_PATTERN, '^$' ) . ')/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'confirm' => array(
						'description' => 'Must be true. Deletes every field definition of the section.',
						'type'        => 'boolean',
						'required'    => true,
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
		$sections = array();
		foreach ( Section::cases() as $section ) {
			$sections[] = array(
				'key'         => $section->value,
				'label'       => $this->label( $section ),
				'is_custom'   => false,
				'field_count' => count( $this->service->list( SectionKey::core( $section ) ) ),
			);
		}

		/**
		 * Sections listed in the admin. Pro appends its custom sections.
		 *
		 * @param list<array<string, mixed>> $sections Core sections.
		 */
		$sections = apply_filters( 'rxcf_rest_sections', $sections );

		return new \WP_REST_Response( is_array( $sections ) ? array_values( $sections ) : array() );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reset( \WP_REST_Request $request ) {
		if ( true !== $request->get_param( 'confirm' ) ) {
			return new \WP_Error( 'rxcf_confirm_required', __( 'Send confirm: true to reset this section.', 'rachnix-checkout-fields' ), array( 'status' => 400 ) );
		}

		try {
			$section = SectionKey::from_string( (string) $request->get_param( 'section' ) );
			$deleted = $this->service->reset_section( $section, get_current_user_id() );
		} catch ( InvalidFieldDefinition ) {
			return new \WP_Error( 'rest_invalid_param', __( 'Unknown section.', 'rachnix-checkout-fields' ), array( 'status' => 400 ) );
		} catch ( DatabaseError $e ) {
			return $this->error_response( $e );
		}

		return new \WP_REST_Response( array( 'deleted' => $deleted ) );
	}

	/**
	 * @param Section $section Core section.
	 */
	private function label( Section $section ): string {
		return match ( $section ) {
			Section::Billing  => __( 'Billing', 'rachnix-checkout-fields' ),
			Section::Shipping => __( 'Shipping', 'rachnix-checkout-fields' ),
			Section::Order    => __( 'Additional information', 'rachnix-checkout-fields' ),
			Section::Contact  => __( 'Contact', 'rachnix-checkout-fields' ),
		};
	}
}
