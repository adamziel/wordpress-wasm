<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\URL;
use Rowbot\URL\URLSearchParams;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-sort.any.js
 */
class URLSearchParamsSortTest extends TestCase
{
    public static function getTestData(): array
    {
        return [
            [
                'input' => 'z=b&a=b&z=a&a=a',
                'output' => [
                    ['a', 'b'],
                    ['a', 'a'],
                    ['z', 'b'],
                    ['z', 'a'],
                ],
            ],
            [
                'input' => "\u{FFFD}=x&\u{FFFC}&\u{FFFD}=a",
                'output' => [
                    ["\u{FFFC}", ''],
                    ["\u{FFFD}", 'x'],
                    ["\u{FFFD}", 'a'],
                ],
            ],
            [
                'input' => 'ﬃ&🌈',
                'output' => [['🌈', ''], ['ﬃ', '']],
            ],
            [
                'input' => "é&e\u{FFFD}&e\u{0301}",
                'output' => [
                    ["e\u{0301}", ''],
                    ["e\u{FFFD}", ''],
                    ['é', ''],
                ],
            ],
            [
                'input' => 'z=z&a=a&z=y&a=b&z=x&a=c&z=w&a=d&z=v&a=e&z=u&a=f&z=t&a=g',
                'output' => [
                    ['a', 'a'],
                    ['a', 'b'],
                    ['a', 'c'],
                    ['a', 'd'],
                    ['a', 'e'],
                    ['a', 'f'],
                    ['a', 'g'],
                    ['z', 'z'],
                    ['z', 'y'],
                    ['z', 'x'],
                    ['z', 'w'],
                    ['z', 'v'],
                    ['z', 'u'],
                    ['z', 't'],
                ],
            ],
            [
                'input' => 'bbb&bb&aaa&aa=x&aa=y',
                'output' => [
                    ['aa', 'x'],
                    ['aa', 'y'],
                    ['aaa', ''],
                    ['bb', ''],
                    ['bbb', ''],
                ],
            ],
            [
                'input' => 'z=z&=f&=t&=x',
                'output' => [['', 'f'], ['', 't'], ['', 'x'], ['z', 'z']],
            ],
            [
                'input' => 'a🌈&a💩',
                'output' => [['a🌈', ''], ['a💩', '']],
            ],
        ];
    }

    public function testSort(string $input, array $output): void
    {
        $url = new URL('?' . $input, 'https://example/');
        $url->searchParams->sort();
        $params = new URLSearchParams($url->search);
        $i = 0;
        foreach ($params as $param) {
            self::assertSame($output[$i++], $param);
        }
    }

    public function testSortingNonExistentParamsRemovesQuestionMark(): void
    {
        $url = new URL('http://example.com/?');
        $url->searchParams->sort();
        self::assertSame('http://example.com/', $url->href);
        self::assertSame('', $url->search);
    }
}
