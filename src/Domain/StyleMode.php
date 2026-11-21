<?php
/**
 * How checkout fields are styled.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Theme: the theme styles everything (default). Plugin: a full field style that only fills
 * gaps the theme leaves. Custom: the store owner's accent, radius, spacing and label position.
 */
enum StyleMode: string {

	case Theme  = 'theme';
	case Plugin = 'plugin';
	case Custom = 'custom';
}
