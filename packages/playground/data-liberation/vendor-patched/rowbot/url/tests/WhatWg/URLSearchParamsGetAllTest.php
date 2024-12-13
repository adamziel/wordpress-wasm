<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URLSearchParams;

use function count;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-getall.any.js
 */
class URLSearchParamsGetAllTest extends TestCase
{
    public function testGetAllBasics(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        self::assertSame(['b'], $params->getAll('a'));
        self::assertSame(['d'], $params->getAll('c'));
        self::assertSame([], $params->getAll('e'));
        $params = new URLSearchParams('a=b&c=d&a=e');
        self::assertSame(['b', 'e'], $params->getAll('a'));
        $params = new URLSearchParams('=b&c=d');
        self::assertSame(['b'], $params->getAll(''));
        $params = new URLSearchParams('a=&c=d&a=e');
        self::assertSame(['', 'e'], $params->getAll('a'));
    }

    public function testGetAllMultiple(): void
    {
        $params = new URLSearchParams('a=1&a=2&a=3&a');
        self::assertTrue($params->has('a'));
        $matches = $params->getAll('a');
        self::assertTrue($matches && count($matches) === 4);
        self::assertSame(['1', '2', '3', ''], $matches);
        $params->set('a', 'one');
        self::assertSame('one', $params->get('a'));
        $matches = $params->getAll('a');
        self::assertTrue($matches && count($matches) === 1);
        self::assertSame(['one'], $matches);
    }
}
