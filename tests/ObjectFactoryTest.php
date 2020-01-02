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

namespace Wikimedia\Test;

use Closure;
use Psr\Container\ContainerInterface;
use Wikimedia\ObjectFactory;

/**
 * @covers \Wikimedia\ObjectFactory
 */
class ObjectFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testClosureExpansionDisabled() {
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [
				function () {
					return 'wrapped';
				},
				'unwrapped',
			],
			'calls' => [
				'setter' => [ function () {
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
				function () {
					return 'wrapped';
				},
				'unwrapped',
			],
			'calls' => [
				'setter' => [ function () {
					return 'wrapped';
				}, ],
			],
			'closure_expansion' => true,
		] );
		$this->assertInternalType( 'string', $obj->args[0] );
		$this->assertSame( 'wrapped', $obj->args[0] );
		$this->assertSame( 'unwrapped', $obj->args[1] );
		$this->assertInternalType( 'string', $obj->setterArgs[0] );
		$this->assertSame( 'wrapped', $obj->setterArgs[0] );

		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ function () {
				return 'unwrapped';
			}, ],
			'calls' => [
				'setter' => [ function () {
					return 'unwrapped';
				}, ],
			],
		] );
		$this->assertInternalType( 'string', $obj->args[0] );
		$this->assertSame( 'unwrapped', $obj->args[0] );
		$this->assertInternalType( 'string', $obj->setterArgs[0] );
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

		$opts = [ 'specIsArg' => true, 'extraArgs' => [ 'foo', 'bar' ] ];
		$spec = [
			'class' => ObjectFactoryTestFixture::class,
			'args' => [ 'a', 'b' ],
		];
		$obj = ObjectFactory::getObjectFromSpec( $spec, $opts );
		$this->assertSame( [ 'foo', 'bar', $spec + [ 'spec_is_arg' => true ] ], $obj->args );

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
			'factory' => function ( $a, $b ) {
				return new ObjectFactoryTestFixture( $a, $b );
			},
			'args' => $args,
		] );
		$this->assertSame( $args, $obj->args );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Provided specification lacks both 'factory' and 'class' parameters.
	 */
	public function testGetObjectFromInvalid() {
		$args = [ 'a', 'b' ];
		$obj = ObjectFactory::getObjectFromSpec( [
			// Missing 'class' or 'factory'
			'args' => $args,
		] );
	}

	/**
	 * @dataProvider provideConstructClassInstance
	 */
	public function testGetObjectFromClass( $args ) {
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => $args,
		] );
		$this->assertSame( $args, $obj->args );
	}

	/**
	 * @covers \Wikimedia\ObjectFactory::constructClassInstance
	 * @dataProvider provideConstructClassInstance
	 */
	public function testConstructClassInstance( $args ) {
		$level = error_reporting();
		error_reporting( $level & ~E_USER_DEPRECATED );
		try {
			$obj = ObjectFactory::constructClassInstance(
				ObjectFactoryTestFixture::class, $args
			);
			$this->assertSame( $args, $obj->args );
		} finally {
			error_reporting( $level );
		}
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

	/**
	 * @covers \Wikimedia\ObjectFactory::constructClassInstance
	 * @expectedException \InvalidArgumentException
	 */
	public function testNamedArgs_old() {
		$level = error_reporting();
		error_reporting( $level & ~E_USER_DEPRECATED );
		try {
			$args = [ 'foo' => 1, 'bar' => 2, 'baz' => 3 ];
			$obj = ObjectFactory::constructClassInstance(
				ObjectFactoryTestFixture::class, $args
			);
		} finally {
			error_reporting( $level );
		}
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage 'args' cannot be an associative array
	 */
	public function testNamedArgs() {
		$args = [ 'foo' => 1, 'bar' => 2, 'baz' => 3 ];
		$obj = ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'args' => $args,
		] );
	}

	/**
	 * @expectedException \UnexpectedValueException
	 * @expectedExceptionMessage 'factory' did not return an object
	 */
	public function testNonObjectFactory() {
		ObjectFactory::getObjectFromSpec( [
			'factory' => function () {
				return null;
			},
		] );
	}

	/**
	 * @expectedException \UnexpectedValueException
	 * @expectedExceptionMessage 'factory' was expected to return an instance of
	 */
	public function testWrongObjectFactory() {
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'factory' => function () {
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

	/**
	 * @expectedException \UnexpectedValueException
	 * @expectedExceptionMessage Expected instance of FooBar, got
	 */
	public function testAssertClass() {
		// This one passes
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
		], [ 'assertClass' => ObjectFactoryTestFixture::class ] );

		// This one fails
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
		], [ 'assertClass' => 'FooBar' ] );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Passing a raw class name is not allowed here
	 */
	public function testClassSpecNotAllowed() {
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

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Passing a raw callable is not allowed here
	 */
	public function testCallableSpecNotAllowed() {
		ObjectFactory::getObjectFromSpec(
			function ( ...$args ) {
				return new ObjectFactoryTestFixture( ...$args );
			},
			[ 'extraArgs' => [ 'foo', 'bar' ] ]
		);
	}

	public function testCallableSpecAllowed() {
		$obj = ObjectFactory::getObjectFromSpec(
			function ( ...$args ) {
				return new ObjectFactoryTestFixture( ...$args );
			},
			[ 'allowCallable' => true, 'extraArgs' => [ 'foo', 'bar' ] ]
		);
		$this->assertSame( [ 'foo', 'bar' ], $obj->args );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Provided specification is not an array.
	 */
	public function testBadSpec() {
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

		$container = $this->getMockBuilder( ContainerInterface::class )
			->setMethods( [ 'get' ] )
			->getMockForAbstractClass();
		$container->method( 'get' )->willReturnCallback( function ( $name ) use ( $services ) {
			if ( isset( $services[$name] ) ) {
				return $services[$name];
			}
			throw new \Exception( "Service $name not found" );
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
			'factory' => function ( ...$args ) {
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

		// Optional service omitted
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
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage 'services' cannot be used without a service container
	 */
	public function testServices_error() {
		ObjectFactory::getObjectFromSpec( [
			'class' => ObjectFactoryTestFixture::class,
			'services' => [ 'foo', 'bar' ],
		] );
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
