<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/failure.html
 */
class FailureTest extends WhatwgTestCase
{
    public static function urlTestDataFailureProvider(): iterable
    {
        foreach (self::loadTestData('urltestdata.json') as $inputs) {
            if (isset($inputs['failure']) && $inputs['base'] === null) {
                yield [$inputs];
            }
        }
    }

    /**
     * URL's constructor's first argument is tested by url-constructorTest. If a
     * URL fails to parse with any valid base, it must also fail to parse with
     * no base, i.e. when used as a base URL itself.
     */
    public function testURLContructor(array $test): void
    {
        $this->expectException(TypeError::class);
        new URL('about:blank', $test['input']);
    }

    public function testUrlHrefSetterThrows(array $test): void
    {
        $this->expectException(TypeError::class);
        $url = new URL('about:blank');
        $url->href = $test['input'];
    }
}
