<?php
/**
 * Where labels sit, in Custom style mode.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Above the field, or beside it on wide screens (full-width rows of the classic checkout).
 */
enum LabelPosition: string {

	case Above = 'above';
	case Side  = 'side';
}
