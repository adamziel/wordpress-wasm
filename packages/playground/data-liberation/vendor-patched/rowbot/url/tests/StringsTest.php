<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\String\Exception\RegexException;
use Rowbot\URL\String\Exception\UndefinedIndexException;
use Rowbot\URL\String\StringList;
use Rowbot\URL\String\Utf8String;
use ValueError;

class StringsTest extends TestCase
{
    public function testTranscodeUnknownEncoding(): void
    {
        $this->expectException(ValueError::class);
        Utf8String::transcode('stuff', 'gallifreyan', 'utf-8');
    }

    public static function startsWithTwoAsciiHexDigitsProvider(): array
    {
        return [
            ['ab', true],
            ['a', false],
            ['99', true],
            ['a3', true],
            ['3a', true],
            ['a4x', true],
            ['AB', true],
            ['3F', true],
            ['gab', false],
            ['', false],
        ];
    }

    public function testStartsWithTwoAsciiHexDigits(string $input, bool $expected): void
    {
        $s = new Utf8String($input);
        self::assertSame($expected, $s->startsWithTwoAsciiHexDigits());
    }

    public static function startsWithWindowsDriveLetterProvider(): array
    {
        return [
            ['c:', true],
            ['c:/', true],
            ['c:a', false],
            ['4:', false],
            ['az:', false],
            ['a|', true],
            ['a:|', false],
            ['', false],
            ['c:\\', true],
            ['c:?', true],
            ['c:#', true],
            ['c:/f', true],
        ];
    }

    public function testStartsWithWindowsDriveLetter(string $input, bool $expected): void
    {
        $s = new Utf8String($input);
        self::assertSame($expected, $s->startsWithWindowsDriveLetter());
    }

    public function testMatchesThrowsWhenOffsetExceedsLength(): void
    {
        $this->expectException(RegexException::class);
        $s = new Utf8String('');
        $s->matches('/[A-Z]/', $matches, 0, 1);
    }

    public function testMatchesThrowsOnInvalidUtf8Text(): void
    {
        $this->expectException(RegexException::class);
        $s = new Utf8String("\xC3\x7F");
        $s->matches('/[A-Z]/u');
    }

    public function testReplaceRegexThrowsOnInvalidUtf8Text(): void
    {
        $this->expectException(RegexException::class);
        $s = new Utf8String("\xC3\x7F");
        $s->replaceRegex('/[A-Z]/u', 'foo');
    }

    public function testSplitReturnsEmptyListWithEmptyDelimiter(): void
    {
        $s = new Utf8String('');
        self::assertTrue($s->split('')->isEmpty());
    }

    public function testStringListFirstThrowsWithEmptyList(): void
    {
        $this->expectException(UndefinedIndexException::class);
        $list = new StringList();
        $list->first();
    }

    public function testStringListLastThrowsWithEmptyList(): void
    {
        $this->expectException(UndefinedIndexException::class);
        $list = new StringList();
        $list->last();
    }

    public function testStringListKeyReturnsInteger(): void
    {
        $s = new Utf8String('a=b=c=d');

        foreach ($s->split('=') as $key => $string) {
            self::assertIsInt($key);
        }
    }
}
