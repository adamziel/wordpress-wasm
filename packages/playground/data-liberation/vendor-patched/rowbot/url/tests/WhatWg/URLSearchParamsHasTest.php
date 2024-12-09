<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-has.any.js
 */
class URLSearchParamsHasTest extends TestCase
{
    public function testHasBasics(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        self::assertTrue($params->has('a'));
        self::assertTrue($params->has('c'));
        self::assertFalse($params->has('e'));
        $params = new URLSearchParams('a=b&c=d&a=e');
        self::assertTrue($params->has('a'));
        $params = new URLSearchParams('=b&c=d');
        self::assertTrue($params->has(''));
    }

    public function testHasFollowingDelete(): void
    {
        $params = new URLSearchParams('a=b&c=d&&');
        $params->append('first', 1);
        $params->append('first', 2);
        self::assertTrue($params->has('a'));
        self::assertTrue($params->has('c'));
        self::assertTrue($params->has('first'));
        self::assertFalse($params->has('d'));
        $params->delete('first');
        self::assertFalse($params->has('first'));
    }

    public function testTwoArgumentHas(): void
    {
        $params = new URLSearchParams('a=b&a=d&c&e&');
        self::assertTrue($params->has('a', 'b'));
        self::assertFalse($params->has('a', 'c'));
        self::assertTrue($params->has('a', 'd'));
        self::assertTrue($params->has('e', ''));
        $params->append('first', 'null');
        self::assertFalse($params->has('first', ''));
        self::assertTrue($params->has('first', 'null'));
        $params->delete('a', 'b');
        self::assertTrue($params->has('a', 'd'));
    }
}
