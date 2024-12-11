<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\URL;

use function json_encode;
use function str_replace;

use const JSON_THROW_ON_ERROR;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/url-tojson.any.js
 */
class URLToJSONTest extends TestCase
{
    public function testBasicToJSON(): void
    {
        $a = new URL('https://example.com');
        self::assertSame('"' . str_replace('/', '\/', $a->href) . '"', json_encode($a, 0));
        self::assertSame('"' . $a->href . '"', $a->toJSON());
    }
}
