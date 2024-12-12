<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use Rowbot\URL\URL;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/url-origin.html
 */
class URLOriginTest extends WhatwgTestCase
{
    public static function urlTestDataOriginProvider(): iterable
    {
        foreach (self::loadTestData('urltestdata.json') as $inputs) {
            if (isset($inputs['origin'])) {
                yield [$inputs];
            }
        }
    }

    public function testOrigin(array $expected): void
    {
        $url = isset($expected['base']) ? new URL($expected['input'], $expected['base']) : new URL($expected['input']);
        self::assertSame($expected['origin'], $url->origin);
    }
}
