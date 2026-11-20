<?php
/**
 * Contract for the list of WooCommerce's own checkout fields.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Contracts;

use Rachnix\CheckoutFields\Domain\CoreFieldDefault;
use Rachnix\CheckoutFields\Domain\SectionKey;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's default checkout fields, before any customisation by this plugin.
 */
interface CoreFieldCatalogInterface {

	/**
	 * @return list<CoreFieldDefault>
	 */
	public function all(): array;

	/**
	 * @param SectionKey $section Section.
	 * @param string     $key     Field key.
	 */
	public function find( SectionKey $section, string $key ): ?CoreFieldDefault;
}
