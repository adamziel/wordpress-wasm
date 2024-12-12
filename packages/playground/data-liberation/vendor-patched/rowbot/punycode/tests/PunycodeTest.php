<?php

declare(strict_types=1);

namespace Rowbot\Punycode\Test;

use PHPUnit\Framework\TestCase;
use Rowbot\Punycode\Exception\InvalidInputException;
use Rowbot\Punycode\Exception\OutputSizeExceededException;
use Rowbot\Punycode\Punycode;

use function mb_strlen;
use function preg_match;

class PunycodeTest extends TestCase
{
    /**
     * @see https://tools.ietf.org/html/rfc3492#section-7.1
     *
     * @return array<int, array<int, string>>
     */
    public static function punycodeProvider(): array
    {
        return [
            [
                'ليهمابتكلموشعربي؟',
                'egbpdaj6bu4bxfgehfvwxn',
                'Arabic (Egyptian)'
            ],
            [
                '他们为什么不说中文',
                'ihqwcrb4cv8a8dqg056pqjye',
                'Chinese (simplified)'
            ],
            [
                '他們爲什麽不說中文',
                'ihqwctvzc91f659drss3x8bo0yb',
                'Chinese (traditional)'
            ],
            [
                'Pročprostěnemluvíčesky',
                'Proprostnemluvesky-uyb24dma41a',
                'Czech'
            ],
            [
                'למההםפשוטלאמדבריםעברית',
                '4dbcagdahymbxekheh6e0a7fei0b',
                'Hebrew'
            ],
            [
                'यहलोगहिन्दीक्योंनहींबोलसकतेहैं',
                'i1baa7eci9glrd9b2ae1bj0hfcgg6iyaf8o0a1dig0cd',
                'Hindi (Devanagari)'
            ],
            [
                'なぜみんな日本語を話してくれないのか',
                'n8jok5ay5dzabd5bym9f0cm5685rrjetr6pdxa',
                'Japanese (kanji and hiragana)'
            ],
            [
                '세계의모든사람들이한국어를이해한다면얼마나좋을까',
                '989aomsvi5e83db1d2a355cv1e0vak1dwrv93d5xbh15a0dt30a5jpsd879ccm6fea98c',
                'Korean (Hangul syllables)'
            ],
            [
                'почемужеонинеговорятпорусски',
                'b1abfaaepdrnnbgefbadotcwatmq2g4l',
                'Russian (Cyrillic)'
            ],
            [
                'PorquénopuedensimplementehablarenEspañol',
                'PorqunopuedensimplementehablarenEspaol-fmd56a',
                'Spanish'
            ],
            [
                'TạisaohọkhôngthểchỉnóitiếngViệt',
                'TisaohkhngthchnitingVit-kjcr8268qyxafd2f1b9g',
                'Vietnamese'
            ],
            [
                '3年B組金八先生',
                '3B-ww4c5e180e575a65lsy2b',
                ''
            ],
            [
                '安室奈美恵-with-SUPER-MONKEYS',
                '-with-SUPER-MONKEYS-pc58ag80a8qai00g7n9n',
                ''
            ],
            [
                'Hello-Another-Way-それぞれの場所',
                'Hello-Another-Way--fc4qua05auwb3674vfr0b',
                ''
            ],
            [
                'ひとつ屋根の下2',
                '2-u9tlzr9756bt3uc0v',
                ''
            ],
            [
                'MajiでKoiする5秒前',
                'MajiKoi5-783gue6qz075azm5e',
                ''
            ],
            [
                'パフィーdeルンバ',
                'de-jg4avhby1noc0d',
                ''
            ],
            [
                'そのスピードで',
                'd9juau41awczczp',
                ''
            ],
            [
                '-> $1.00 <-',
                '-> $1.00 <--',
                ''
            ],
        ];
    }

    /**
     * @dataProvider punycodeProvider
     */
    public function testEncode(string $decoded, string $encoded, string $comment): void
    {
        self::assertSame($encoded, Punycode::encode($decoded), $comment);
    }

    /**
     * @dataProvider punycodeProvider
     */
    public function testDecode(string $decoded, string $encoded, string $comment): void
    {
        self::assertSame($decoded, Punycode::decode($encoded), $comment);
    }

    public function encodeMaxLengthDataProvider(): array
    {
        return array_filter($this->punycodeProvider(), static function (array $data): bool {
            return mb_strlen($data[1], 'utf-8') > 10;
        });
    }

    /**
     * @dataProvider encodeMaxLengthDataProvider
     */
    public function testEncodeMaxLength(string $decoded, string $encoded, string $comment): void
    {
        $this->expectException(OutputSizeExceededException::class);
        Punycode::encode($decoded, 10);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function decodeMaxLengthDataProvider(): array
    {
        return array_filter($this->punycodeProvider(), static function (array $data): bool {
            return mb_strlen($data[0], 'utf-8') > 10;
        });
    }

    /**
     * @dataProvider decodeMaxLengthDataProvider
     */
    public function testDecodeMaxLength(string $decoded, string $encoded, string $comment): void
    {
        $this->expectException(OutputSizeExceededException::class);
        Punycode::decode($encoded, 10);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function nonAsciiDataProvider(): array
    {
        return array_filter($this->punycodeProvider(), static function (array $data): bool {
            return preg_match('/[^\x00-\x7F]/', $data[0]) === 1;
        }) + [
            ["abc\u{FFFD}", '', 'Mixed ASCII and non-ASCII']
        ];
    }

    /**
     * @dataProvider nonAsciiDataProvider
     */
    public function testDecodeOnlyAcceptsAscii(string $decoded, string $encoded, string $comment): void
    {
        $this->expectException(InvalidInputException::class);
        Punycode::decode($decoded);
    }

    public function testCaseFlags(): void
    {
        $c = [true, true, true];
        $e = Punycode::encode('abc', null, $c);
        self::assertSame('ABC-', $e);

        $c = [false, false, false];
        $d = Punycode::decode($e, null, $c);
        self::assertSame([true, true, true], $c);
        self::assertSame('ABC', $d);

        $c = [];
        $d = Punycode::decode($e, null, $c);
        self::assertSame([true, true, true], $c);
        self::assertSame('ABC', $d);
    }
}
