<?php
/**
 * Checkout sections built into WooCommerce.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A core checkout section. Custom sections (Pro) are referenced through SectionKey.
 */
enum Section: string {

	case Billing  = 'billing';
	case Shipping = 'shipping';
	case Order    = 'order';
	case Contact  = 'contact';
}
