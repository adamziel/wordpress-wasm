<?php

declare(strict_types=1);

namespace Rowbot\Punycode;

use Rowbot\Punycode\Exception\InvalidInputException;
use Rowbot\Punycode\Exception\OutputSizeExceededException;
use Rowbot\Punycode\Exception\OverflowException;

use function array_map;
use function array_splice;
use function chr;
use function func_num_args;
use function implode;
use function intdiv;
use function ord;
use function str_split;
use function strlen;
use function strrpos;

/**
 * @see https://tools.ietf.org/html/rfc3492
 * @see https://github.com/bestiejs/punycode.js/blob/master/punycode.js
 * @see https://github.com/unicode-org/icu/blob/master/icu4c/source/common/punycode.cpp
 * @see https://github.com/unicode-org/icu/blob/master/icu4j/main/classes/core/src/com/ibm/icu/impl/Punycode.java
 */
final class Punycode
{
    private const BASE         = 36;
    private const TMIN         = 1;
    private const TMAX         = 26;
    private const SKEW         = 38;
    private const DAMP         = 700;
    private const INITIAL_BIAS = 72;
    private const INITIAL_N    = 128;
    private const DELIMITER    = '-';
    private const MAX_INT      = 2147483647;

    /**
     * Contains the numeric value of a basic code point (for use in representing integers) in the
     * range 0 to BASE-1, or -1 if b is does not represent a value.
     *
     * @var array<int, int>
     */
    private static $basicToDigit = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,

        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1, -1,

        -1,  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14,
        15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, -1, -1, -1, -1, -1,

        -1,  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14,
        15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, -1, -1, -1, -1, -1,

        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,

        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,

        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,

        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
    ];

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * @see https://tools.ietf.org/html/rfc3492#section-6.1
     */
    private static function adaptBias(int $delta, int $numPoints, bool $firstTime): int
    {
        // xxx >> 1 is a faster way of doing intdiv(xxx, 2)
        $delta = $firstTime ? intdiv($delta, self::DAMP) : $delta >> 1;
        $delta += intdiv($delta, $numPoints);
        $k = 0;

        while ($delta > ((self::BASE - self::TMIN) * self::TMAX) >> 1) {
            $delta = intdiv($delta, self::BASE - self::TMIN);
            $k += self::BASE;
        }

        return $k + intdiv((self::BASE - self::TMIN + 1) * $delta, $delta + self::SKEW);
    }

    /**
     * @see https://tools.ietf.org/html/rfc3492#section-6.2
     *
     * @param array<int, bool> $caseFlags
     */
    public static function decode(string $input, ?int $outLength = null, array &$caseFlags = []): string
    {
        $n = self::INITIAL_N;
        $out = 0;
        $i = 0;
        $maxOut = $outLength ?? self::MAX_INT;
        $bias = self::INITIAL_BIAS;
        $lastDelimIndex = strrpos($input, self::DELIMITER);
        $b = $lastDelimIndex === false ? 0 : $lastDelimIndex;
        $inputLength = strlen($input);
        $output = [];
        $wantsCaseFlags = func_num_args() > 2;

        if ($b > $maxOut) {
            throw new OutputSizeExceededException();
        }

        $bytes = array_map('ord', str_split($input));

        for ($j = 0; $j < $b; ++$j) {
            if ($bytes[$j] > 0x7F) {
                throw new InvalidInputException();
            }

            if ($wantsCaseFlags) {
                $caseFlags[$out] = self::flagged($bytes[$j]);
            }

            $output[$out++] = $input[$j];
        }

        if ($b > 0) {
            $b += 1;
        }

        for ($in = $b; $in < $inputLength; ++$out) {
            $oldi = $i;
            $w = 1;

            for ($k = self::BASE; /* no condition */; $k += self::BASE) {
                if ($in >= $inputLength) {
                    throw new InvalidInputException();
                }

                $digit = self::$basicToDigit[$bytes[$in++] & 0xFF];

                if ($digit < 0) {
                    throw new InvalidInputException();
                }

                if ($digit > intdiv(self::MAX_INT - $i, $w)) {
                    throw new OverflowException();
                }

                $i += $digit * $w;

                if ($k <= $bias) {
                    $t = self::TMIN;
                } elseif ($k >= $bias + self::TMAX) {
                    $t = self::TMAX;
                } else {
                    $t = $k - $bias;
                }

                if ($digit < $t) {
                    break;
                }

                $baseMinusT = self::BASE - $t;

                if ($w > intdiv(self::MAX_INT, $baseMinusT)) {
                    throw new OverflowException();
                }

                $w *= $baseMinusT;
            }

            $outPlusOne = $out + 1;
            $bias = self::adaptBias($i - $oldi, $outPlusOne, $oldi === 0);

            if (intdiv($i, $outPlusOne) > self::MAX_INT - $n) {
                throw new OverflowException();
            }

            $n += intdiv($i, $outPlusOne);
            $i %= $outPlusOne;

            if ($out >= $maxOut) {
                throw new OutputSizeExceededException();
            }

            if ($wantsCaseFlags) {
                array_splice($caseFlags, $i, 0, [self::flagged($bytes[$n - 1])]);
            }

            array_splice($output, $i++, 0, [self::encodeCodePoint($n)]);
        }

        return implode('', $output);
    }

    /**
     * @see https://tools.ietf.org/html/rfc3492#section-6.3
     *
     * @param array<int, bool> $caseFlags
     */
    public static function encode(string $input, ?int $outLength = null, array $caseFlags = []): string
    {
        $n = self::INITIAL_N;
        $delta = 0;
        $out = 0;
        $maxOut = $outLength ?? self::MAX_INT;
        $bias = self::INITIAL_BIAS;
        $inputLength = 0;
        $output = '';
        $codePoints = self::utf8Decode($input);

        foreach ($codePoints as $j => $codePoint) {
            ++$inputLength;

            if ($codePoint < 0x80) {
                if ($maxOut - $out < 2) {
                    throw new OutputSizeExceededException();
                }

                $output .= isset($caseFlags[$j])
                    ? self::encodeBasic($codePoint, $caseFlags[$j])
                    : chr($codePoint);
                ++$out;
            }
        }

        $h = $out;
        $b = $out;

        if ($b > 0) {
            $output .= self::DELIMITER;
            ++$out;
        }

        while ($h < $inputLength) {
            $m = self::MAX_INT;

            foreach ($codePoints as $codePoint) {
                if ($codePoint >= $n && $codePoint < $m) {
                    $m = $codePoint;
                }
            }

            if ($m - $n > intdiv(self::MAX_INT - $delta, $h + 1)) {
                throw new OverflowException();
            }

            $delta += ($m - $n) * ($h + 1);
            $n = $m;

            foreach ($codePoints as $j => $codePoint) {
                if ($codePoint < $n && ++$delta === 0) {
                    throw new OverflowException();
                } elseif ($codePoint === $n) {
                    $q = $delta;

                    for ($k = self::BASE; /* no condition */; $k += self::BASE) {
                        if ($out >= $maxOut) {
                            throw new OutputSizeExceededException();
                        }

                        if ($k <= $bias) {
                            $t = self::TMIN;
                        } elseif ($k >= $bias + self::TMAX) {
                            $t = self::TMAX;
                        } else {
                            $t = $k - $bias;
                        }

                        if ($q < $t) {
                            break;
                        }

                        $qMinusT = $q - $t;
                        $baseMinusT = self::BASE - $t;
                        $output .= self::encodeDigit($t + ($qMinusT) % ($baseMinusT), false);
                        ++$out;
                        $q = intdiv($qMinusT, $baseMinusT);
                    }

                    $output .= self::encodeDigit($q, $caseFlags[$j] ?? false);
                    ++$out;
                    $bias = self::adaptBias($delta, $h + 1, $h === $b);
                    $delta = 0;
                    ++$h;
                }
            }

            ++$delta;
            ++$n;
        }

        return $output;
    }

    private static function encodeBasic(int $codePoint, bool $flag): string
    {
        $codePoint -= ($codePoint - 97 < 26 ? 1 : 0) << 5;

        return chr($codePoint + ((!$flag && ($codePoint - 65 < 26) ? 1 : 0) << 5));
    }

    /**
     * Takes a Unicode code point and encodes it. The return behavior is undefined if the given
     * code point is outside the range 0..10FFFF.
     *
     * @see https://encoding.spec.whatwg.org/#utf-8-encoder
     */
    private static function encodeCodePoint(int $codePoint): string
    {
        if ($codePoint >= 0x00 && $codePoint <= 0x7F) {
            return chr($codePoint);
        }

        $count = 0;
        $offset = 0;

        if ($codePoint >= 0x0080 && $codePoint <= 0x07FF) {
            $count = 1;
            $offset = 0xC0;
        } elseif ($codePoint >= 0x0800 && $codePoint <= 0xFFFF) {
            $count = 2;
            $offset = 0xE0;
        } elseif ($codePoint >= 0x10000 && $codePoint <= 0x10FFFF) {
            $count = 3;
            $offset = 0xF0;
        }

        $bytes = chr(($codePoint >> (6 * $count)) + $offset);

        while ($count > 0) {
            $temp = $codePoint >> (6 * ($count - 1));
            $bytes .= chr(0x80 | ($temp & 0x3F));
            --$count;
        }

        return $bytes;
    }

    private static function encodeDigit(int $d, bool $flag): string
    {
        return chr($d + 22 + 75 * ($d < 26 ? 1 : 0) - (($flag ? 1 : 0) << 5));
    }

    private static function flagged(int $codePoint): bool
    {
        return $codePoint - 65 < 26;
    }

    /**
     * Takes a UTF-8 encoded string and converts it into a series of integer code points. Any
     * invalid byte sequences will be replaced by a U+FFFD replacement code point.
     *
     * @see https://encoding.spec.whatwg.org/#utf-8-decoder
     *
     * @return array<int, int>
     */
    private static function utf8Decode(string $input): array
    {
        $bytesSeen = 0;
        $bytesNeeded = 0;
        $lowerBoundary = 0x80;
        $upperBoundary = 0xBF;
        $codePoint = 0;
        $codePoints = [];
        $length = strlen($input);

        for ($i = 0; $i < $length; ++$i) {
            $byte = ord($input[$i]);

            if ($bytesNeeded === 0) {
                if ($byte <= 0x7F) {
                    $codePoints[] = $byte;

                    continue;
                }

                if ($byte >= 0xC2 && $byte <= 0xDF) {
                    $bytesNeeded = 1;
                    $codePoint = $byte & 0x1F;
                } elseif ($byte >= 0xE0 && $byte <= 0xEF) {
                    if ($byte === 0xE0) {
                        $lowerBoundary = 0xA0;
                    } elseif ($byte === 0xED) {
                        $upperBoundary = 0x9F;
                    }

                    $bytesNeeded = 2;
                    $codePoint = $byte & 0xF;
                } elseif ($byte >= 0xF0 && $byte <= 0xF4) {
                    if ($byte === 0xF0) {
                        $lowerBoundary = 0x90;
                    } elseif ($byte === 0xF4) {
                        $upperBoundary = 0x8F;
                    }

                    $bytesNeeded = 3;
                    $codePoint = $byte & 0x7;
                } else {
                    $codePoints[] = 0xFFFD;
                }

                continue;
            }

            if ($byte < $lowerBoundary || $byte > $upperBoundary) {
                $codePoint = 0;
                $bytesNeeded = 0;
                $bytesSeen = 0;
                $lowerBoundary = 0x80;
                $upperBoundary = 0xBF;
                --$i;
                $codePoints[] = 0xFFFD;

                continue;
            }

            $lowerBoundary = 0x80;
            $upperBoundary = 0xBF;
            $codePoint = ($codePoint << 6) | ($byte & 0x3F);

            if (++$bytesSeen !== $bytesNeeded) {
                continue;
            }

            $codePoints[] = $codePoint;
            $codePoint = 0;
            $bytesNeeded = 0;
            $bytesSeen = 0;
        }

        // String unexpectedly ended, so append a U+FFFD code point.
        if ($bytesNeeded !== 0) {
            $codePoints[] = 0xFFFD;
        }

        return $codePoints;
    }
}
