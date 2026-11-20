<?php
/**
 * Kinds of validation a store owner can add to a field.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * "Required" is a field setting, not a rule. Rules are checked by ValidationService.
 */
enum ValidationRuleType: string {

	case Email       = 'email';
	case Phone       = 'phone';
	case NumberRange = 'number_range';
	case Length      = 'length';
	case Pattern     = 'pattern';
}
