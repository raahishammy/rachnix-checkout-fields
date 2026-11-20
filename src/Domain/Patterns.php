<?php
/**
 * Allowed formats for identifiers.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Whitelist patterns shared by the domain objects. Anything else is rejected.
 */
final class Patterns {

	/** Lowercase UUID, as produced by wp_generate_uuid4(). */
	public const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

	/** Field key, as stored in `rxcf_fields.field_key`. */
	public const FIELD_KEY = '/^[a-z][a-z0-9_]{1,63}$/';

	/** Field type slug, as stored in `rxcf_fields.type`. */
	public const TYPE_SLUG = '/^[a-z][a-z0-9_]{1,31}$/';

	/** One CSS class name. */
	public const CSS_CLASS = '/^[A-Za-z_-][A-Za-z0-9_-]{0,63}$/';

	/** Id of a curated validation pattern. */
	public const PATTERN_ID = '/^[a-z][a-z0-9_]{0,63}$/';

	private function __construct() {}
}
