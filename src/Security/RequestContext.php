<?php
/**
 * Facts about the current request.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Security;

defined( 'ABSPATH' ) || exit;

/**
 * The raw IP address never leaves this class.
 */
final class RequestContext {

	/**
	 * HMAC-SHA-256 of the client IP keyed with the site's auth salt, or '' when unknown.
	 * Uses REMOTE_ADDR only: forwarded headers can be spoofed by the client.
	 */
	public function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
