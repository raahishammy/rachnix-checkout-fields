<?php
/**
 * Field width on the checkout.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Full row or half row.
 */
enum Width: string {

	case Full = 'full';
	case Half = 'half';
}
