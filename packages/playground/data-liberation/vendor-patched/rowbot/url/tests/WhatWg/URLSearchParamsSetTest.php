<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-set.any.js
 */
class URLSearchParamsSetTest extends TestCase
{
    public function testSetBasics(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        $params->set('a', 'B');
        self::assertSame('a=B&c=d', $params . '');
        $params = new URLSearchParams('a=b&c=d&a=e');
        $params->set('a', 'B');
        self::assertSame('a=B&c=d', $params . '');
        $params->set('e', 'f');
        self::assertSame('a=B&c=d&e=f', $params . '');
    }

    public function testURLSearchParamsSet(): void
    {
        $params = new URLSearchParams('a=1&a=2&a=3');

        self::assertTrue(
            $params->has('a'),
            'Search params object has name "a"'
        );
        self::assertSame(
            '1',
            $params->get('a'),
            'Search params object has name "a" with a value of "1"'
        );

        $params->set('first', 4);

        self::assertTrue(
            $params->has('a'),
            'Search params object has name "a"'
        );
        self::assertSame(
            '1',
            $params->get('a'),
            'Search params object has name "a" with value "1"'
        );

        $params->set('a', 4);

        self::assertTrue(
            $params->has('a'),
            'Search params object has name "a"'
        );
        self::assertSame(
            '4',
            $params->get('a'),
            'Search params object has name "a" with value "4"'
        );
    }
}
