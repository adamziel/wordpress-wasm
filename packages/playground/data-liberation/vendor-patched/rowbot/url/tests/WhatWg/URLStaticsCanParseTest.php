<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\URL;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/url-statics-canparse.any.js
 */
class URLStaticsCanParseTest extends TestCase
{
    public function testCanParse(string $url, ?string $base, bool $expected): void
    {
        self::assertSame($expected, URL::canParse($url, $base));
    }
}
