<?php
/**
 * PHPUnit bootstrap for unit tests. WordPress is not loaded; Brain Monkey mocks its functions.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
