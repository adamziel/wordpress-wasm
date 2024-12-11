<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URL;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-delete.any.js
 */
class URLSearchParamsDeleteTest extends TestCase
{
    public function testDeleteBasics(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        $params->delete('a');
        self::assertSame('c=d', $params . '');
        $params = new URLSearchParams('a=a&b=b&a=a&c=c');
        $params->delete('a');
        self::assertSame('b=b&c=c', $params . '');
        $params = new URLSearchParams('a=a&=&b=b&c=c');
        $params->delete('');
        self::assertSame('a=a&b=b&c=c', $params . '');
    }

    public function testDeleteAppendMultiple(): void
    {
        $params = new URLSearchParams();
        $params->append('first', 1);
        self::assertTrue($params->has('first'));
        self::assertSame('1', $params->get('first'));
        $params->delete('first');
        self::assertFalse($params->has('first'));
        $params->append('first', 1);
        $params->append('first', 10);
        $params->delete('first');
        self::assertFalse($params->has('first'));
    }

    public function testDeleteAllRemovesQuestionMark(): void
    {
        $url = new URL('http://example.com/?param1&param2');
        $url->searchParams->delete('param1');
        $url->searchParams->delete('param2');
        self::assertSame('http://example.com/', $url->href);
        self::assertSame('', $url->search);
    }

    public function testDeleteNonExistentParamRemovesQuestionMark(): void
    {
        $url = new URL('http://example.com/?');
        $url->searchParams->delete('param1');
        self::assertSame('http://example.com/', $url->href);
        self::assertSame('', $url->search);
    }

    public function testChangingTheQueryOfAUrlWithAnOpaquePathCanImpactThePath(): void
    {
        $url = new URL('data:space    ?test');
        self::assertTrue($url->searchParams->has('test'));
        $url->searchParams->delete('test');
        self::assertFalse($url->searchParams->has('test'));
        self::assertSame('', $url->search);
        self::assertSame('space', $url->pathname);
        self::assertSame('data:space', $url->href);
    }

    public function testChangingTheQueryOfAUrlWithAnOpaquePathCanImpactThePathIfTheUrlHasNoFragment(): void
    {
        $url = new URL('data:space    ?test#test');
        $url->searchParams->delete('test');
        self::assertSame('', $url->search);
        self::assertSame('space    ', $url->pathname);
        self::assertSame('data:space    #test', $url->href);
    }

    public function testTwoArgumentDelete(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b');
        $params->append('a', 'c');
        $params->append('a', 'd');
        $params->delete('a', 'c');
        self::assertSame('a=b&a=d', $params->toString());
    }
}
