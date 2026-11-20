<?php
/**
 * Contract for classes that attach WordPress hooks.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Constructors never add hooks; `register()` does, so hooks are explicit and testable.
 */
interface Hookable {

	/**
	 * Adds this object's actions and filters.
	 */
	public function register(): void;
}
