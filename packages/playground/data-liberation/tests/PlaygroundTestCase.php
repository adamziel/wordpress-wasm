<?php

use PHPUnit\Framework\TestCase;

/**
 * Base class for Playground tests.
 */
abstract class PlaygroundTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || $_SERVER['SERVER_SOFTWARE'] !== 'PHP.wasm' ) {
			$this->markTestSkipped( 'Test only runs in Playground' );
		}
	}
}
