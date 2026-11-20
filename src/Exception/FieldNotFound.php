<?php
/**
 * Unknown field.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a field UUID does not exist.
 */
final class FieldNotFound extends \RuntimeException {}
