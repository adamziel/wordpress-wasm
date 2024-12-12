<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-append.any.js
 */
class URLSearchParamsAppendTest extends TestCase
{
    public function testAppendSameName(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b');
        self::assertSame('a=b', $params . '');
        $params->append('a', 'b');
        self::assertSame('a=b&a=b', $params . '');
        $params->append('a', 'c');
        self::assertSame('a=b&a=b&a=c', $params . '');
    }

    public function testAppendEmptyString(): void
    {
        $params = new URLSearchParams();
        $params->append('', '');
        self::assertSame('=', $params . '');
        $params->append('', '');
        self::assertSame('=&=', $params . '');
        $params->append('a', 'c');
    }

    public function testAppendMultiple(): void
    {
        $params = new URLSearchParams();
        $params->append('first', 1);
        $params->append('second', 2);
        $params->append('third', '');
        $params->append('first', 10);
        self::assertTrue($params->has('first'));
        self::assertSame('1', $params->get('first'));
        self::assertSame('2', $params->get('second'));
        self::assertSame('', $params->get('third'));
        $params->append('first', 10);
        self::assertSame('1', $params->get('first'));
    }
}
