<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit;

use Rachnix\CheckoutFields\Container;
use Rachnix\CheckoutFields\Exception\ServiceNotFound;

final class ContainerTest extends TestCase {

	public function test_get_builds_the_service_once_and_shares_it(): void {
		$container = new Container();
		$calls     = 0;
		$container->set(
			\ArrayObject::class,
			static function () use ( &$calls ): \ArrayObject {
				++$calls;
				return new \ArrayObject();
			}
		);

		$this->assertSame( $container->get( \ArrayObject::class ), $container->get( \ArrayObject::class ) );
		$this->assertSame( 1, $calls );
	}

	public function test_factory_is_lazy(): void {
		$container = new Container();
		$container->set( \ArrayObject::class, fn() => $this->fail( 'Factory ran before get().' ) );

		$this->assertTrue( $container->has( \ArrayObject::class ) );
	}

	public function test_factory_receives_the_container(): void {
		$container = new Container();
		$container->set( \SplObjectStorage::class, static fn() => new \SplObjectStorage() );
		$container->set(
			\ArrayObject::class,
			static fn( Container $c ) => new \ArrayObject( array( $c->get( \SplObjectStorage::class ) ) )
		);

		$this->assertSame( $container->get( \SplObjectStorage::class ), $container->get( \ArrayObject::class )[0] );
	}

	public function test_set_replaces_an_already_built_service(): void {
		$container = new Container();
		$container->set( \ArrayObject::class, static fn() => new \ArrayObject( array( 'old' ) ) );
		$container->get( \ArrayObject::class );
		$container->set( \ArrayObject::class, static fn() => new \ArrayObject( array( 'new' ) ) );

		$this->assertSame( 'new', $container->get( \ArrayObject::class )[0] );
	}

	public function test_unknown_service_throws(): void {
		$this->expectException( ServiceNotFound::class );
		( new Container() )->get( \ArrayObject::class );
	}

	public function test_factory_returning_wrong_type_throws(): void {
		$container = new Container();
		$container->set( \ArrayObject::class, static fn() => new \stdClass() );

		$this->expectException( ServiceNotFound::class );
		$container->get( \ArrayObject::class );
	}
}
