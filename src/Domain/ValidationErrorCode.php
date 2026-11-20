<?php
/**
 * Why a submitted value was rejected.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Error kinds returned by ValidationService.
 */
enum ValidationErrorCode: string {

	case Required        = 'required';
	case TooShort        = 'too_short';
	case TooLong         = 'too_long';
	case InvalidEmail    = 'invalid_email';
	case InvalidPhone    = 'invalid_phone';
	case InvalidNumber   = 'invalid_number';
	case OutOfRange      = 'out_of_range';
	case InvalidDate     = 'invalid_date';
	case InvalidChoice   = 'invalid_choice';
	case PatternMismatch = 'pattern_mismatch';
}
