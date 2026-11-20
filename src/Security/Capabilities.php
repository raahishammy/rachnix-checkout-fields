<?php
/**
 * The plugin's capability.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Security;

use Rachnix\CheckoutFields\Contracts\Hookable;

defined( 'ABSPATH' ) || exit;

/**
 * Grants `rxcf_manage_fields` to every user who can manage WooCommerce, at runtime,
 * so no role is changed in the database. A role editor can still deny it explicitly.
 */
final class Capabilities implements Hookable {

	public const MANAGE_FIELDS = 'rxcf_manage_fields';

	public const GRANTED_BY = 'manage_woocommerce';

	public function register(): void {
		add_filter( 'user_has_cap', array( $this, 'grant' ) );
	}

	/**
	 * @param array<string, bool> $allcaps The user's capabilities.
	 * @return array<string, bool>
	 */
	public function grant( array $allcaps ): array {
		if ( ! array_key_exists( self::MANAGE_FIELDS, $allcaps ) && ! empty( $allcaps[ self::GRANTED_BY ] ) ) {
			$allcaps[ self::MANAGE_FIELDS ] = true;
		}
		return $allcaps;
	}
}
