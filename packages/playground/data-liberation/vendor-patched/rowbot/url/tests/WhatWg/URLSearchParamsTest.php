<?php

namespace Rowbot\URL\Tests\WhatWg;

use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/url-searchparams.any.js
 */
class URLSearchParamsTest extends WhatwgTestCase
{
    public function testURLSearchParamsGetter(): void
    {
        $url = new URL('http://example.org/?a=b');
        self::assertNotNull($url->searchParams);
        $searchParams = $url->searchParams;
        self::assertSame($searchParams, $url->searchParams);
    }

    /**
     * Test URL.searchParams updating, clearing.
     */
    public function testURLSearchParamsUpdatingClearing(): void
    {
        $url = new URL('http://example.org/?a=b', 'about:blank');
        self::assertNotNull($url->searchParams);
        $searchParams = $url->searchParams;
        self::assertSame('a=b', $searchParams->toString());

        $searchParams->set('a', 'b');
        self::assertSame('a=b', $url->searchParams->toString());
        self::assertSame('?a=b', $url->search);
        $url->search = '';
        self::assertSame('', $url->searchParams->toString());
        self::assertSame('', $url->search);
        self::assertSame('', $searchParams->toString());
    }

    public function testURLSearchParamsSetterInvalidValues(): void
    {
        $this->expectException(TypeError::class);
        $urlString = 'http://example.org';
        $url = new URL($urlString, 'about:blank');
        $url->searchParams = new URLSearchParams($urlString);
    }

    public function testURLSearchParamsAndURLSearchSettersUpdatePropagation(): void
    {
        $url = new URL('http://example.org/file?a=b&c=d');
        self::assertInstanceOf(URLSearchParams::class, $url->searchParams);
        $searchParams = $url->searchParams;
        self::assertSame('?a=b&c=d', $url->search);
        self::assertSame('a=b&c=d', $searchParams->toString());

        // Test that setting 'search' propagates to the URL object's query
        // object
        $url->search = 'e=f&g=h';
        self::assertSame('?e=f&g=h', $url->search);
        self::assertSame('e=f&g=h', $url->searchParams->toString());

        // ...and same, but with a leading '?'
        $url->search = '?e=f&g=h';
        self::assertSame('?e=f&g=h', $url->search);
        self::assertSame('e=f&g=h', $url->searchParams->toString());

        // And in the other direction, altering searchParams propagates back
        // to 'search'
        $searchParams->append('i', ' j ');
        self::assertSame('?e=f&g=h&i=+j+', $url->search);
        self::assertSame('e=f&g=h&i=+j+', $url->searchParams->toString());
        self::assertSame(' j ', $searchParams->get('i'));

        $searchParams->set('e', 'updated');
        self::assertSame('?e=updated&g=h&i=+j+', $url->search);
        self::assertSame('e=updated&g=h&i=+j+', $url->searchParams->__toString());

        $url2 = new URL('http://example.org/file??a=b&c=d', 'about:blank');
        self::assertSame('??a=b&c=d', $url2->search);
        self::assertSame('%3Fa=b&c=d', $url2->searchParams->toString());

        $url2->href = 'http://example.org/file??a=b';
        self::assertSame('??a=b', $url2->search);
        self::assertSame('%3Fa=b', $url2->searchParams->toString());
    }
}
