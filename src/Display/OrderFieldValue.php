<?php
/**
 * One custom field's value on one order.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Display;

use Rachnix\CheckoutFields\Domain\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Where the value came from matters for display: WooCommerce shows block checkout values
 * on some screens itself, so this plugin only adds the classic ones there.
 */
final readonly class OrderFieldValue {

	public const SOURCE_CLASSIC = 'classic';

	public const SOURCE_BLOCKS = 'blocks';

	/**
	 * @param Field  $field  Field definition.
	 * @param string $value  Stored value.
	 * @param string $source SOURCE_CLASSIC or SOURCE_BLOCKS.
	 */
	public function __construct(
		public Field $field,
		public string $value,
		public string $source,
	) {}

	public function is_classic(): bool {
		return self::SOURCE_CLASSIC === $this->source;
	}
}
