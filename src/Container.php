<?php
/**
 * Minimal dependency injection container.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields;

use Rachnix\CheckoutFields\Exception\ServiceNotFound;

defined( 'ABSPATH' ) || exit;

/**
 * Holds lazy, shared services keyed by class or interface name.
 */
final class Container {

	/**
	 * @var array<string, callable(Container): object>
	 */
	private array $factories = array();

	/**
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Registers a factory. The service is built on first `get()` and then reused.
	 *
	 * @param string                     $id      Class or interface name.
	 * @param callable(Container):object $factory Builds the service.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $id Class or interface name.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Returns the shared service.
	 *
	 * @template T of object
	 * @param class-string<T> $id Class or interface name.
	 * @return T
	 * @throws ServiceNotFound When nothing is registered under `$id`, or the factory returns the wrong type.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			// Exception messages hold class names from our own code, never user input, and are never printed.
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw ServiceNotFound::for_id( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$service = ( $this->factories[ $id ] )( $this );
			if ( ! $service instanceof $id ) {
				throw ServiceNotFound::wrong_type( $id, $service::class ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$this->instances[ $id ] = $service;
		}

		/** @var T */
		return $this->instances[ $id ];
	}
}
