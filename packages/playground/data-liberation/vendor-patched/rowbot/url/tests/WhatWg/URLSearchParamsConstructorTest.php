<?php

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URLSearchParams;
use stdClass;

use function json_decode;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;

/**
 * @see https://github.com/web-platform-tests/wpt/blob/master/url/urlsearchparams-constructor.any.js
 */
class URLSearchParamsConstructorTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $params = new URLSearchParams();
        self::assertSame('', $params . '');
        $params = new URLSearchParams('');
        self::assertSame('', $params . '');
        $params = new URLSearchParams('a=b');
        self::assertSame('a=b', $params . '');
        $params = new URLSearchParams($params);
        self::assertSame('a=b', $params . '');
    }

    public function testConstructorNoArguments(): void
    {
        $params = new URLSearchParams();
        self::assertSame('', $params->toString());
    }

    public function testRemovingLeadingQuestionMark(): void
    {
        $params = new URLSearchParams('?a=b');
        self::assertSame('a=b', $params->toString());
    }

    public function testConstructorEmptyObject(): void
    {
        $params = new URLSearchParams(new stdClass());
        self::assertSame('', (string) $params);
    }

    public function testConstructorString(): void
    {
        $params = new URLSearchParams('a=b');
        self::assertNotNull($params);
        self::assertTrue($params->has('a'));
        self::assertFalse($params->has('b'));
        $params = new URLSearchParams('a=b&c');
        self::assertNotNull($params);
        self::assertTrue($params->has('a'));
        self::assertTrue($params->has('c'));
        $params = new URLSearchParams('&a&&& &&&&&a+b=& c&m%c3%b8%c3%b8');
        self::assertNotNull($params);
        self::assertTrue($params->has('a'));
        self::assertTrue($params->has('a b'));
        self::assertTrue($params->has(' '));
        self::assertFalse($params->has('c'));
        self::assertTrue($params->has(' c'));
        self::assertTrue($params->has('møø'));

        $params = new URLSearchParams('id=0&value=%');
        self::assertNotNull($params);
        self::assertTrue($params->has('id'));
        self::assertTrue($params->has('value'));
        self::assertSame('0', $params->get('id'));
        self::assertSame('%', $params->get('value'));

        $params = new URLSearchParams('b=%2sf%2a');
        self::assertNotNull($params);
        self::assertTrue($params->has('b'));
        self::assertSame('%2sf*', $params->get('b'));

        $params = new URLSearchParams('b=%2%2af%2a');
        self::assertNotNull($params);
        self::assertTrue($params->has('b'));
        self::assertSame('%2*f*', $params->get('b'));

        $params = new URLSearchParams('b=%%2a');
        self::assertNotNull($params);
        self::assertTrue($params->has('b'));
        self::assertSame('%*', $params->get('b'));
    }

    public function testConstructorObject(): void
    {
        $seed = new URLSearchParams('a=b&c=d');
        $params = new URLSearchParams($seed);
        self::assertNotNull($params, 'message');
        self::assertSame('b', $params->get('a'));
        self::assertSame('d', $params->get('c'));
        self::assertFalse($params->has('d'), 'message');
        // The name-value pairs are copied when  created; later, updates should
        // not be observable.
        $seed->append('e', 'f');
        self::assertFalse($params->has('e'));
        $params->append('g', 'h');
        self::assertFalse($seed->has('g'));
    }

    public function testParsePlusSign(): void
    {
        $params = new URLSearchParams('a=b+c');
        self::assertSame('b c', $params->get('a'));
        $params = new URLSearchParams('a+b=c');
        self::assertSame('c', $params->get('a b'));
    }

    public function testParsePlusSignPercentEncoded(): void
    {
        $testValue = '+15555555555';
        $params = new URLSearchParams();
        $params->set('query', $testValue);

        $newParams = new URLSearchParams($params->toString());
        self::assertSame('query=%2B15555555555', $params->toString());
        self::assertSame($testValue, $params->get('query'));
        self::assertSame($testValue, $newParams->get('query'));
    }

    public function testParseSpace(): void
    {
        $params = new URLSearchParams('a=b c');
        self::assertSame('b c', $params->get('a'));
        $params = new URLSearchParams('a b=c');
        self::assertSame('c', $params->get('a b'));
    }

    public function testParseSpacePercentEncoded(): void
    {
        $params = new URLSearchParams('a=b%20c');
        self::assertSame('b c', $params->get('a'));
        $params = new URLSearchParams('a%20b=c');
        self::assertSame('c', $params->get('a b'));
    }

    public function testParseNullByte(): void
    {
        $params = new URLSearchParams("a=b\0c");
        self::assertSame("b\0c", $params->get('a'));
        $params = new URLSearchParams("a\0b=c");
        self::assertSame('c', $params->get("a\0b"));
    }

    public function testParseNullBytePercentEncoded(): void
    {
        $params = new URLSearchParams('a=b%00c');
        self::assertSame("b\0c", $params->get('a'));
        $params = new URLSearchParams('a%00b=c');
        self::assertSame('c', $params->get("a\0b"));
    }

    public function testParseUnicodeCompositionSymbol(): void
    {
        $params = new URLSearchParams("a=b\u{2384}");
        self::assertSame("b\u{2384}", $params->get('a'));
        $params = new URLSearchParams("a\u{2384}=c");
        self::assertSame('c', $params->get("a\u{2384}"));
    }

    public function testParseUnicodeCompositionSymbolPercentEncoded(): void
    {
        $params = new URLSearchParams('a=b%E2%8E%84');
        self::assertSame("b\u{2384}", $params->get('a'));
        $params = new URLSearchParams('a%E2%8E%84=c');
        self::assertSame('c', $params->get("a\u{2384}"));
    }

    public function testParseUnicodePileOfPoo(): void
    {
        $params = new URLSearchParams("a=b\u{1F4A9}c");
        self::assertSame("b\u{1F4A9}c", $params->get('a'));
        $params = new URLSearchParams("a\u{1F4A9}b=c");
        self::assertSame('c', $params->get("a\u{1F4A9}b"));
    }

    public function testParseUnicodePileOfPooPercentEncoded(): void
    {
        $params = new URLSearchParams('a=b%f0%9f%92%a9c');
        self::assertSame("b\u{1F4A9}c", $params->get('a'));
        $params = new URLSearchParams('a%f0%9f%92%a9b=c');
        self::assertSame('c', $params->get("a\u{1F4A9}b"));
    }

    public function testSequenceOfSequences(): void
    {
        $params = new URLSearchParams([]);
        self::assertNotNull($params);
        $params = new URLSearchParams([['a', 'b'], ['c', 'd']]);
        self::assertSame('b', $params->get('a'));
        self::assertSame('d', $params->get('c'));

        try {
            new URLSearchParams([[1]]);
            self::assertTrue(false);
        } catch (TypeError $exception) {
            self::assertTrue(true);
        }

        try {
            new URLSearchParams([[1, 2, 3]]);
            self::assertTrue(false);
        } catch (TypeError $exception) {
            self::assertTrue(true);
        }
    }

    public static function getTestData(): array
    {
        $obj = new stdClass();
        $obj->{'+'} = '%C2';

        $obj2 = new stdClass();
        $obj2->c = 'x';
        $obj2->a = '?';

        $obj3 = new stdClass();
        $obj3->{"a\0b"} = '42';
        $obj3->{"c\u{D83D}"} = '23';
        $obj3->{"d\u{1234}"} = 'foo';

        // Mimic error handling of JavaScript Object keys
        $json = json_encode($obj3, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(json_last_error_msg());
        }
        $obj3 = json_decode($json, false, 512, 0);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(json_last_error_msg());
        }

        return [
            ['input' => $obj, 'output' => [['+', '%C2']]],
            [
                'input' => $obj2,
                'output' => [
                    ['c', 'x'],
                    ['a', '?'],
                ],
            ],
            [
                'input' => [
                    ['c', 'x'],
                    ['a', '?'],
                ],
                'output' => [
                    ['c', 'x'],
                    ['a', '?'],
                ],
            ],
            [
                'input' => $obj3,
                'output' => [
                    ["a\0b", '42'],
                    ["c\u{FFFD}", '23'],
                    ["d\u{1234}", 'foo'],
                ],
            ],
        ];
    }

    public function test($input, array $output): void
    {
        $params = new URLSearchParams($input);
        $i = 0;
        foreach ($params as $param) {
            self::assertSame($output[$i++], $param);
        }
    }
}
