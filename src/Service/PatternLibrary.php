<?php
/**
 * Curated, ReDoS-safe validation patterns.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Service;

defined( 'ABSPATH' ) || exit;

/**
 * The only regular expressions the free plugin runs on customer input. Store owners
 * pick one by id; they cannot enter their own regex.
 *
 * Rules for every entry (enforced by PatternLibraryTest):
 * - anchored with ^ and $, with the D modifier so a trailing newline cannot match;
 * - no nested or adjacent overlapping quantifiers, so matching time is linear;
 * - matches its own example.
 */
final class PatternLibrary {

	/**
	 * @var array<string, array{regex: string, example: string}>
	 */
	private const PATTERNS = array(
		'letters'         => array(
			'regex'   => "/^[\\p{L}\\p{M}][\\p{L}\\p{M} '.-]*$/uD",
			'example' => "Anne-Marie O'Neil",
		),
		'letters_numbers' => array(
			'regex'   => '/^[\p{L}\p{M}\p{N}]+$/uD',
			'example' => 'Order42',
		),
		'digits'          => array(
			'regex'   => '/^[0-9]+$/D',
			'example' => '123456',
		),
		'code'            => array(
			'regex'   => '/^[A-Za-z0-9_-]+$/D',
			'example' => 'SUMMER-24',
		),
		'postcode_uk'     => array(
			'regex'   => '/^[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2}$/iD',
			'example' => 'SW1A 1AA',
		),
		'zip_us'          => array(
			'regex'   => '/^[0-9]{5}(?:-[0-9]{4})?$/D',
			'example' => '12345-6789',
		),
		'pin_in'          => array(
			'regex'   => '/^[1-9][0-9]{5}$/D',
			'example' => '560001',
		),
		'vat_eu'          => array(
			'regex'   => '/^[A-Z]{2}[A-Z0-9]{2,12}$/iD',
			'example' => 'DE123456789',
		),
		'iban'            => array(
			'regex'   => '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/iD',
			'example' => 'DE89370400440532013000',
		),
	);

	/**
	 * @return list<string>
	 */
	public function ids(): array {
		return array_keys( self::PATTERNS );
	}

	/**
	 * @param string $id Pattern id.
	 */
	public function regex( string $id ): ?string {
		return self::PATTERNS[ $id ]['regex'] ?? null;
	}

	/**
	 * Example of a valid value, shown in error messages and the admin.
	 *
	 * @param string $id Pattern id.
	 */
	public function example( string $id ): ?string {
		return self::PATTERNS[ $id ]['example'] ?? null;
	}

	/**
	 * Translated name for the admin picker.
	 *
	 * @param string $id Pattern id.
	 */
	public function label( string $id ): ?string {
		return match ( $id ) {
			'letters'         => __( 'Letters only (names)', 'rachnix-checkout-fields' ),
			'letters_numbers' => __( 'Letters and numbers', 'rachnix-checkout-fields' ),
			'digits'          => __( 'Digits only', 'rachnix-checkout-fields' ),
			'code'            => __( 'Code (letters, numbers, dashes, underscores)', 'rachnix-checkout-fields' ),
			'postcode_uk'     => __( 'UK postcode', 'rachnix-checkout-fields' ),
			'zip_us'          => __( 'US ZIP code', 'rachnix-checkout-fields' ),
			'pin_in'          => __( 'Indian PIN code', 'rachnix-checkout-fields' ),
			'vat_eu'          => __( 'EU VAT number', 'rachnix-checkout-fields' ),
			'iban'            => __( 'IBAN (no spaces)', 'rachnix-checkout-fields' ),
			default           => null,
		};
	}
}
