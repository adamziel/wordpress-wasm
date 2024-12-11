<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use Rowbot\URL\URL;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/url-setters.html
 */
class URLSettersTest extends WhatwgTestCase
{
    public static function urlSetterGetterDataProvider(): iterable
    {
        foreach (self::loadTestData('setters_tests.json') as $key => $tests) {
            if ($key === 'comment') {
                continue;
            }

            foreach ($tests as $inputs) {
                unset($inputs['comment']);
                $inputs['setter'] = $key;

                yield [$inputs];
            }
        }
    }

    public function testSetters(array $input): void
    {
        $url = new URL($input['href']);
        $url->{$input['setter']} = $input['new_value'];
        foreach ($input['expected'] as $attribute => $value) {
            self::assertSame($value, $url->$attribute, $attribute);
        }
    }
}
