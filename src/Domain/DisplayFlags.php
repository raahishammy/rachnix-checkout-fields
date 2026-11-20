<?php
/**
 * Where a field's value is shown after checkout.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Per-field "Show in" switches.
 */
final readonly class DisplayFlags {

	/**
	 * @param bool $order_admin    Order edit screen.
	 * @param bool $my_account     Customer's My Account order view.
	 * @param bool $thank_you      Thank-you page.
	 * @param bool $admin_email    Emails to the store.
	 * @param bool $customer_email Emails to the customer.
	 * @param bool $order_list     Column in the orders list (at most two fields).
	 */
	public function __construct(
		public bool $order_admin = true,
		public bool $my_account = true,
		public bool $thank_you = true,
		public bool $admin_email = true,
		public bool $customer_email = true,
		public bool $order_list = false,
	) {}

	/**
	 * Reads stored data; missing or non-boolean entries keep their defaults.
	 *
	 * @param array<mixed> $data Decoded JSON.
	 */
	public static function from_array( array $data ): self {
		$defaults = new self();
		$flag     = static fn( string $key, bool $fallback ): bool => is_bool( $data[ $key ] ?? null ) ? $data[ $key ] : $fallback;

		return new self(
			$flag( 'order_admin', $defaults->order_admin ),
			$flag( 'my_account', $defaults->my_account ),
			$flag( 'thank_you', $defaults->thank_you ),
			$flag( 'admin_email', $defaults->admin_email ),
			$flag( 'customer_email', $defaults->customer_email ),
			$flag( 'order_list', $defaults->order_list ),
		);
	}

	/**
	 * @return array<string, bool>
	 */
	public function to_array(): array {
		return array(
			'order_admin'    => $this->order_admin,
			'my_account'     => $this->my_account,
			'thank_you'      => $this->thank_you,
			'admin_email'    => $this->admin_email,
			'customer_email' => $this->customer_email,
			'order_list'     => $this->order_list,
		);
	}
}
