<?php
/**
 * Draft previews of the checkout.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

use Rachnix\CheckoutFields\Domain\FieldCollection;
use Rachnix\CheckoutFields\Exception\DatabaseError;
use Rachnix\CheckoutFields\Exception\InvalidFieldInput;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps one user's unpublished changes (the same operations "Publish changes" would send)
 * for an hour, so the real checkout page can be shown with them in the admin's preview frame.
 * The draft itself lives in the browser; this is only a short-lived copy for previewing.
 */
final class PreviewService {

	public const QUERY_ARG = 'rxcf_preview';

	public const NONCE_ARG = '_rxcfnonce';

	public const NONCE_ACTION = 'rxcf_preview';

	private const TTL = HOUR_IN_SECONDS;

	/**
	 * @param FieldService $fields Field changes.
	 */
	public function __construct(
		private readonly FieldService $fields,
	) {}

	/**
	 * Validates the draft, stores it for the user and returns the preview URL.
	 *
	 * @param list<mixed> $operations Unpublished changes.
	 * @param int         $user_id    Current user.
	 * @throws InvalidFieldInput When a change is not acceptable.
	 * @throws DatabaseError     When the database fails.
	 */
	public function start( array $operations, int $user_id ): string {
		$this->fields->preview( $operations, $user_id );
		set_transient( self::key( $user_id ), $operations, self::TTL );

		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
		return add_query_arg(
			array(
				self::QUERY_ARG => '1',
				self::NONCE_ARG => wp_create_nonce( self::NONCE_ACTION ),
			),
			$checkout
		);
	}

	/**
	 * The fields as the user's draft would make them, or null when there is no valid draft.
	 *
	 * @param int $user_id Current user.
	 */
	public function fields_for( int $user_id ): ?FieldCollection {
		$operations = get_transient( self::key( $user_id ) );
		if ( ! is_array( $operations ) ) {
			return null;
		}
		try {
			return $this->fields->preview( array_values( $operations ), $user_id );
		} catch ( InvalidFieldInput | DatabaseError ) {
			return null; // The stored fields changed since; the admin asks for a new preview.
		}
	}

	/**
	 * @param int $user_id User.
	 */
	private static function key( int $user_id ): string {
		return 'rxcf_preview_' . $user_id;
	}
}
