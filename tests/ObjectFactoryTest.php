<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace Wikimedia\ObjectFactory\Test;

use Closure;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * @covers \Wikimedia\ObjectFactory\ObjectFactory
 */
class ObjectFactoryTest extends TestCase {

	public function testClosureExpansionDisabled() {
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [
				static function () {
					return 'wrapped';
				},
				'unwrapped',
			],
			'calls' => [
				'setter' => [ static function () {
					return 'wrapped';
				}, ],
			],
			'closure_expansion' => false,
		] );
		$this->assertInstanceOf( Closure::class, $obj->args[0] );
		$this->assertSame( 'wrapped', $obj->args[0]() );
		$this->assertSame( 'unwrapped', $obj->args[1] );
		$this->assertInstanceOf( Closure::class, $obj->setterArgs[0] );
		$this->assertSame( 'wrapped', $obj->setterArgs[0]() );
	}

	public function testClosureExpansionEnabled() {
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [
				static function () {
					return 'wrapped';
				},
				'unwrapped',
			],
			'calls' => [
				'setter' => [ static function () {
					return 'wrapped';
				}, ],
			],
			'closure_expansion' => true,
		] );
		$this->assertIsString( $obj->args[0] );
		$this->assertSame( 'wrapped', $obj->args[0] );
		$this->assertSame( 'unwrapped', $obj->args[1] );
		$this->assertIsString( $obj->setterArgs[0] );
		$this->assertSame( 'wrapped', $obj->setterArgs[0] );

		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ static function () {
				return 'unwrapped';
			}, ],
			'calls' => [
				'setter' => [ static function () {
					return 'unwrapped';
				}, ],
			],
		] );
		$this->assertIsString( $obj->args[0] );
		$this->assertSame( 'unwrapped', $obj->args[0] );
		$this->assertIsString( $obj->setterArgs[0] );
		$this->assertSame( 'unwrapped', $obj->setterArgs[0] );
	}

	public function testSpecIsArg() {
		$spec = [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ 'a', 'b' ],
			'spec_is_arg' => true,
		];
		$obj = ObjectFactory::getObjectFromSpec( $spec );
		$this->assertSame( [ $spec ], $obj->args );

		// spec_is_arg defaults to false
		$opts = [ 'extraArgs' => [ 'foo', 'bar' ] ];
		$spec = [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ 'a', 'b' ],
		];
		$obj = ObjectFactory::getObjectFromSpec( $spec, $opts );
		$this->assertSame( [ 'foo', 'bar', 'a', 'b' ], $obj->args );

		$spec = [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ 'a', 'b' ],
			'spec_is_arg' => false,
		];
		$obj = ObjectFactory::getObjectFromSpec( $spec, $opts );
		$this->assertSame( [ 'foo', 'bar', 'a', 'b' ], $obj->args );
	}

	public function testGetObjectFromFactory() {
		$args = [ 'a', 'b' ];
		$obj = ObjectFactory::getObjectFromSpec( [
			'factory' => static function ( $a, $b ) {
				return new ObjectFactoryTestFixture( $a, $b );
			},
			'args' => $args,
		] );
		$this->assertSame( $args, $obj->args );
	}

	public function testGetObjectFromInvalid() {
		$args = [ 'a', 'b' ];
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'Provided specification lacks both \'factory\' and \'class\' parameters.'
		);
		ObjectFactory::getObjectFromSpec( [
			// Missing 'class' or 'factory'
			'args' => $args,
		] );
	}

	/**
	 * @covers \Wikimedia\ObjectFactory\ObjectFactory::getObjectFromSpec
	 * @dataProvider provideConstructClassInstance
	 */
	public function testGetObjectFromClass( $args ) {
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => $args,
		] );
		$this->assertSame( $args, $obj->args );
	}

	public static function provideConstructClassInstance() {
		// These args go to 11. I thought about making 10 one louder, but 11!
		return [
			'0 args' => [ [] ],
			'1 args' => [ [ 1, ] ],
			'2 args' => [ [ 1, 2, ] ],
			'3 args' => [ [ 1, 2, 3, ] ],
			'4 args' => [ [ 1, 2, 3, 4, ] ],
			'5 args' => [ [ 1, 2, 3, 4, 5, ] ],
			'6 args' => [ [ 1, 2, 3, 4, 5, 6, ] ],
			'7 args' => [ [ 1, 2, 3, 4, 5, 6, 7, ] ],
			'8 args' => [ [ 1, 2, 3, 4, 5, 6, 7, 8, ] ],
			'9 args' => [ [ 1, 2, 3, 4, 5, 6, 7, 8, 9, ] ],
			'10 args' => [ [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, ] ],
			'11 args' => [ [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, ] ],
		];
	}

	public function testNamedArgs() {
		$args = [ 'foo' => 1, 'bar' => 2, 'baz' => 3 ];
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '\'args\' cannot be an associative array' );
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => $args,
		] );
	}

	public function testNonObjectFactory() {
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( '\'factory\' did not return an object' );
		ObjectFactory::getObjectFromSpec( [
			'factory' => static function () {
				return null;
			},
		] );
	}

	public function testWrongObjectFactory() {
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( '\'factory\' was expected to return an instance of' );
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'factory' => static function () {
				return new \stdClass;
			},
		] );
	}

	public function testExtraArgs() {
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ 'a', 'b' ],
		], [ 'extraArgs' => [ 'foo', 'bar' ] ] );
		$this->assertSame( [ 'foo', 'bar', 'a', 'b' ], $obj->args );
	}

	public function testAssertClass() {
		// This one passes
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
		], [ 'assertClass' => ObjectFactoryTestFixture::class ] );

		// This one fails
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Expected instance of FooBar, got' );
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
		], [ 'assertClass' => 'FooBar' ] );
	}

	public function testClassSpecNotAllowed() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Passing a raw class name is not allowed here' );
		ObjectFactory::getObjectFromSpec(
			ObjectFactoryTestFixture::class,
			[ 'extraArgs' => [ 'foo', 'bar' ] ]
		);
	}

	public function testClassSpecAllowed() {
		$obj = ObjectFactory::getObjectFromSpec(
			ObjectFactoryTestFixture::class,
			[ 'allowClassName' => true, 'extraArgs' => [ 'foo', 'bar' ] ]
		);
		$this->assertSame( [ 'foo', 'bar' ], $obj->args );
	}

	public function testCallableSpecNotAllowed() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Passing a raw callable is not allowed here' );
		ObjectFactory::getObjectFromSpec(
			static function ( ...$args ) {
				return new ObjectFactoryTestFixture( ...$args );
			},
			[ 'extraArgs' => [ 'foo', 'bar' ] ]
		);
	}

	public function testCallableSpecAllowed() {
		$obj = ObjectFactory::getObjectFromSpec(
			static function ( ...$args ) {
				return new ObjectFactoryTestFixture( ...$args );
			},
			[ 'allowCallable' => true, 'extraArgs' => [ 'foo', 'bar' ] ]
		);
		$this->assertSame( [ 'foo', 'bar' ], $obj->args );
	}

	public function testBadSpec() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Provided specification is not an array.' );
		ObjectFactory::getObjectFromSpec(
			'ThisDoesNotExist',
			[ 'allowClassName' => true,  'allowCallable' => true ]
		);
	}

	public function testServices() {
		$services = [
			'Foo' => (object)[ 'foo' ],
			'Bar' => (object)[ 'bar' ],
			'Baz' => (object)[ 'baz' ],
		];

		$container = $this->createMock( ContainerInterface::class );
		$container->method( 'get' )->willReturnCallback( static function ( $name ) use ( $services ) {
			if ( isset( $services[$name] ) ) {
				return $services[$name];
			}
			throw new Exception( "Service $name not found" );
		} );
		$container->method( 'has' )->willReturnCallback( static function ( $name ) use ( $services ) {
			return isset( $services[$name] );
		} );

		// Basic usage
		$obj = ObjectFactory::getObjectFromSpec(
			[
				'class' => ObjectFactoryTestFixture::class,
				'services' => [ 'Foo' ],
			],
			[
				'serviceContainer' => $container,
			]
		);
		$this->assertSame( [ $services['Foo'] ], $obj->args );

		// Ordering of argument sources
		$obj = ObjectFactory::getObjectFromSpec(
			[
				'class' => ObjectFactoryTestFixture::class,
				'args' => [ 'a', 'b' ],
				'services' => [ 'Bar', 'Foo' ],
			],
			[
				'extraArgs' => [ 'x', 'y' ],
				'serviceContainer' => $container,
			]
		);
		$this->assertSame( [ 'x', 'y', $services['Bar'], $services['Foo'], 'a', 'b' ], $obj->args );

		// Repetition of a service
		$spec = [
			'factory' => static function ( ...$args ) {
				return new ObjectFactoryTestFixture( ...$args );
			},
			'spec_is_arg' => true,
			'services' => [ 'Baz', 'Baz' ],
		];
		$obj = ObjectFactory::getObjectFromSpec( $spec, [
			'extraArgs' => [ 'x', 'y' ],
			'serviceContainer' => $container,
		] );
		$this->assertSame( [ 'x', 'y', $services['Baz'], $services['Baz'], $spec ], $obj->args );

		// Null service passed as null
		$obj = ObjectFactory::getObjectFromSpec(
			[
				'class' => ObjectFactoryTestFixture::class,
				'services' => [ null, 'Foo', null, 'Bar' ],
			],
			[
				'serviceContainer' => $container,
			]
		);
		$this->assertSame( [ null, $services['Foo'], null, $services['Bar'] ], $obj->args );

		// Optional services
		$obj = ObjectFactory::getObjectFromSpec(
			[
				'class' => ObjectFactoryTestFixture::class,
				'optional_services' => [ 'ServiceThatDoesNotExist', 'Foo' ]
			],
			[
				'serviceContainer' => $container
			]
		);
		$this->assertSame( [ null, $services['Foo'] ], $obj->args );

		// Order of arguments with optional and non-optional services
		$obj = ObjectFactory::getObjectFromSpec(
			[
				'class' => ObjectFactoryTestFixture::class,
				'args' => [ 'param1', 'param2' ],
				'services' => [ 'Bar', 'Foo' ],
				'optional_services' => [ 'ServiceThatDoesNotExist', 'Foo' ],
			],
			[
				'extraArgs' => [ 'extra1', 'extra2' ],
				'serviceContainer' => $container,
			]
		);
		$this->assertSame(
			[
				'extra1',
				'extra2',
				$services['Bar'],
				$services['Foo'],
				null,
				$services['Foo'],
				'param1',
				'param2'
			],
			$obj->args
		);
	}

	/**
	 * @dataProvider provideTestMissingServiceContainer
	 * @param string $type either 'services' or 'optional_services'
	 */
	public function testServices_error( $type ) {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'\'services\' and \'optional_services\' cannot be used without a service container'
		);

		$spec = [ 'class' => ObjectFactoryTestFixture::class ];
		$spec[ $type ] = [ 'foo', 'bar' ];
		ObjectFactory::getObjectFromSpec( $spec );
	}

	public function provideTestMissingServiceContainer() {
		return [
			'Normal required services' => [ 'services' ],
			'Optional services' => [ 'optional_services' ],
		];
	}

	public function testNonStaticUse() {
		$container = $this->getMockBuilder( ContainerInterface::class )
			->getMockForAbstractClass();

		// Can't mock a static method, but we can make an anonymous subclass overriding it.
		$factory = new class ( $container ) extends ObjectFactory {
			public static function getObjectFromSpec( $spec, array $options = [] ) {
				$that = $options['that'];
				$that->assertArrayHasKey( 'serviceContainer', $options );
				$that->assertInstanceOf( ContainerInterface::class, $options['serviceContainer'] );
				return parent::getObjectFromSpec( $spec, $options );
			}
		};

		$obj = $factory->createObject( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ 'a', 'b' ],
		], [ 'that' => $this ] );
		$this->assertSame( [ 'a', 'b' ], $obj->args );
	}

}
