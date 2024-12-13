<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;

use function array_key_exists;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/url-constructor.html
 */
class URLConstructorTest extends WhatwgTestCase
{
    public static function urlTestDataSuccessProvider(): iterable
    {
        foreach (self::loadTestData('urltestdata.json') as $inputs) {
            if (isset($inputs['base']) && !isset($inputs['failure'])) {
                yield [$inputs];
            }
        }
    }

    public function testUrlConstructorSucceeded(array $expected): void
    {
        $base = $expected['base'] ? $expected['base'] : 'about:blank';
        $url = new URL($expected['input'], $base);
        self::assertSame($expected['href'], $url->href, 'href');
        self::assertSame($expected['protocol'], $url->protocol, 'protocol');
        self::assertSame($expected['username'], $url->username, 'username');
        self::assertSame($expected['password'], $url->password, 'password');
        self::assertSame($expected['host'], $url->host, 'host');
        self::assertSame($expected['hostname'], $url->hostname, 'hostname');
        self::assertSame($expected['port'], $url->port, 'port');
        self::assertSame($expected['pathname'], $url->pathname, 'pathname');
        self::assertSame($expected['search'], $url->search, 'search');
        if (array_key_exists('searchParams', $expected)) {
            self::assertTrue((bool) $url->searchParams);
            self::assertSame($expected['searchParams'], $url->searchParams->toString(), 'searchParams');
        }
        self::assertSame($expected['hash'], $url->hash, 'hash');
    }

    public static function urlTestDataFailureProvider(): iterable
    {
        foreach (self::loadTestData('urltestdata.json') as $inputs) {
            if (isset($inputs['failure'])) {
                yield [$inputs];
            }
        }
    }

    public function testUrlConstructorFailed(array $expected): void
    {
        $this->expectException(TypeError::class);
        isset($expected['base']) ? new URL($expected['input'], $expected['base']) : new URL($expected['input']);
    }
}
