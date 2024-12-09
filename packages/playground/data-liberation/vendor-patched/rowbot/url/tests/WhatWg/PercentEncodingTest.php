<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests\WhatWg;

use PHPUnit\Framework\Attributes\DataProvider;
use Rowbot\URL\BasicURLParser;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;
use Rowbot\URL\String\Utf8String;

class PercentEncodingTest extends WhatwgTestCase
{
    public function testPercentEncoding(string $input, array $output): void
    {
        $parser = new BasicURLParser();
        $in = new Utf8String("https://doesnotmatter.invalid/?{$input}#{$input}");
        foreach ($output as $encoding => $expected) {
            $url = $parser->parse($in, null, $encoding);

            self::assertNotFalse($url);
            self::assertSame($expected, $url->query, $encoding);
            self::assertSame($output['utf-8'], $url->fragment);
        }
    }

    public static function percentEncodedDataProvider(): iterable
    {
        foreach (self::loadTestData('percent-encoding.json') as $data) {
            // Skip tests for encodings where mbstring produces a result that is different from what is expected
            foreach (['iso-2022-jp', 'gb18030'] as $encoding) {
                if (isset($data['output'][$encoding])) {
                    unset($data['output'][$encoding]);
                }
            }

            yield $data;
        }
    }

    /**
     * @see https://url.spec.whatwg.org/#example-percent-encode-operations
     * @param mixed $encodeSet
     */
    public function testPercentEncodingExamples(string $encoding, string $input, string $output, $encodeSet, bool $spaceAsPlus): void
    {
        $percentEncoder = new PercentEncoder();
        $result = $percentEncoder->percentEncodeAfterEncoding($encoding, $input, $encodeSet, $spaceAsPlus);
        self::assertSame($output, $result);
    }

    public static function exampleDataProvider(): array
    {
        return [
            ['encoding' => 'Shift_JIS', 'input' => ' ', 'output' => '%20', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
            ['encoding' => 'Shift_JIS', 'input' => '≡', 'output' => '%81%DF', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
            ['encoding' => 'Shift_JIS', 'input' => '‽', 'output' => '%26%238253%3B', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
            // ['encoding' => 'ISO-2022-JP', 'input' => '¥', 'output' => '%1B(J\%1B(B', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
            ['encoding' => 'Shift_JIS', 'input' => '1+1 ≡ 2%20‽', 'output' => '1+1+%81%DF+2%20%26%238253%3B', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => true],
            ['encoding' => 'UTF-8', 'input' => '≡', 'output' => '%E2%89%A1', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
            ['encoding' => 'UTF-8', 'input' => '‽', 'output' => '%E2%80%BD', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
            ['encoding' => 'UTF-8', 'input' => 'Say what‽', 'output' => 'Say%20what%E2%80%BD', 'encodeSet' => EncodeSet::USERINFO, 'spaceAsPlus' => false],
        ];
    }
}
