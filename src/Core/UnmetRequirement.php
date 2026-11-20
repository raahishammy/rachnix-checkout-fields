<?php
/**
 * A dependency that is missing or too old.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Value object describing one failed requirement.
 */
final readonly class UnmetRequirement {

	/**
	 * @param Dependency  $dependency Which dependency failed.
	 * @param string|null $found      Installed version, or null when it is missing or inactive.
	 */
	public function __construct(
		public Dependency $dependency,
		public ?string $found,
	) {}

	/**
	 * Whether the dependency is missing or inactive, rather than too old.
	 */
	public function is_missing(): bool {
		return null === $this->found;
	}
}
