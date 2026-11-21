<?php
/**
 * Polylang function signatures, for PHPStan only (never loaded by WordPress).
 *
 * @package Rachnix\CheckoutFields
 */

/**
 * @param string $name      String name.
 * @param string $string    Text.
 * @param string $context   Group shown in Polylang's list.
 * @param bool   $multiline Whether the text has several lines.
 */
function pll_register_string( $name, $string, $context = 'Polylang', $multiline = false ): void {}

/**
 * @param string $string Text.
 * @return string Translation in the current language.
 */
function pll__( $string ) {
	return $string;
}
