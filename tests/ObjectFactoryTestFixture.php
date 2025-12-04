<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\ObjectFactory\Test;

class ObjectFactoryTestFixture {
	/**
	 * @var mixed Arguments passed to constructor
	 */
	public $args;

	/**
	 * @var mixed Arguments passed to setter
	 */
	public $setterArgs;

	/**
	 * @param mixed ...$args
	 */
	public function __construct( ...$args ) {
		$this->args = $args;
	}

	/**
	 * Dependency injection setter stub.
	 * @param mixed ...$setterArgs
	 */
	public function setter( ...$setterArgs ) {
		$this->setterArgs = $setterArgs;
	}
}
