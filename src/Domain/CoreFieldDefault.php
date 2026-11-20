<?php
/**
 * A WooCommerce checkout field as WooCommerce defines it.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's own settings for a core field, used as the starting point when a
 * store owner customises it.
 */
final readonly class CoreFieldDefault {

	/**
	 * @param SectionKey $section  Section.
	 * @param string     $key      WooCommerce field key, e.g. billing_company.
	 * @param string     $label    WooCommerce's (translated) label.
	 * @param bool       $required Required by default.
	 * @param int        $priority Default position.
	 * @param Width      $width    Default width.
	 */
	public function __construct(
		public SectionKey $section,
		public string $key,
		public string $label,
		public bool $required,
		public int $priority,
		public Width $width,
	) {}
}
