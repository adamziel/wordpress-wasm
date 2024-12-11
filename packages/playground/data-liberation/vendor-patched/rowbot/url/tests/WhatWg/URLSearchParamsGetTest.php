<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-get.any.js
 */
class URLSearchParamsGetTest extends TestCase
{
    public function testGetBasics(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        self::assertSame('b', $params->get('a'));
        self::assertSame('d', $params->get('c'));
        self::assertNull($params->get('e'));
        $params = new URLSearchParams('a=b&c=d&a=e');
        self::assertSame('b', $params->get('a'));
        $params = new URLSearchParams('=b&c=d');
        self::assertSame('b', $params->get(''));
        $params = new URLSearchParams('a=&c=d&a=e');
        self::assertSame('', $params->get('a'));
    }

    public function testMoreGetBasics(): void
    {
        $params = new URLSearchParams('first=second&third&&');

        self::assertNotNull($params);
        self::assertTrue($params->has('first'), 'constructor returned non-null value.');
        self::assertSame('second', $params->get('first'), 'Search params object has name "first"');
        self::assertSame('', $params->get('third'), 'Search params object has name "third" with the empty value.');
        self::assertNull($params->get('fourth'), 'Search params object has no "fourth" name and value.');
    }
}
