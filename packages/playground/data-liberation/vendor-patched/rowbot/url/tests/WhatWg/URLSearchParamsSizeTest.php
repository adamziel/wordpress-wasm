<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URL;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-size.any.js
 */
class URLSearchParamsSizeTest extends TestCase
{
    public function testSizeAndDeletion(): void
    {
        $params = new URLSearchParams('a=1&b=2&a=3');
        self::assertSame(3, $params->size);
        self::assertCount(3, $params);

        $params->delete('a');
        self::assertSame(1, $params->size);
        self::assertCount(1, $params);
    }

    public function testSizeAndAddition(): void
    {
        $params = new URLSearchParams('a=1&b=2&a=3');
        self::assertSame(3, $params->size);
        self::assertCount(3, $params);

        $params->append('b', '4');
        self::assertSame(4, $params->size);
        self::assertCount(4, $params);
    }

    public function testSizeWhenObtainedFromAURL(): void
    {
        $url = new URL("http://localhost/query?a=1&b=2&a=3");
        self::assertSame(3, $url->searchParams->size);
        self::assertCount(3, $url->searchParams);

        $url->searchParams->delete('a');
        self::assertSame(1, $url->searchParams->size);
        self::assertCount(1, $url->searchParams);

        $url->searchParams->append('b', '4');
        self::assertSame(2, $url->searchParams->size);
        self::assertCount(2, $url->searchParams);
    }

    public function testSizeWhenObtainedFromAURLAndUsingSearch(): void
    {
        $url = new URL("http://localhost/query?a=1&b=2&a=3");
        self::assertSame(3, $url->searchParams->size);
        self::assertCount(3, $url->searchParams);

        $url->search = '?';
        self::assertSame(0, $url->searchParams->size);
        self::assertCount(0, $url->searchParams);
    }
}
