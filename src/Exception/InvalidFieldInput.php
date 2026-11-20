<?php
/**
 * Rejected field definition from an admin request or import.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Carries one translated, admin-facing message per invalid parameter.
 */
final class InvalidFieldInput extends \InvalidArgumentException {

	/**
	 * @var array<string, string>
	 */
	private array $errors = array();

	/**
	 * @param array<string, string> $errors Parameter path (e.g. "settings.options.0.value") => message.
	 */
	public static function with( array $errors ): self {
		$exception         = new self( __( 'Some field settings are not valid.', 'rachnix-checkout-fields' ) );
		$exception->errors = $errors;
		return $exception;
	}

	/**
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}
}
