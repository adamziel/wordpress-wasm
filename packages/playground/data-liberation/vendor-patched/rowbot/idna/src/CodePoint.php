<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use function chr;
use function ord;
use function strlen;

final class CodePoint
{
    /**
     * Takes a Unicode code point and encodes it. The return behavior is undefined if the given
     * code point is outside the range 0..10FFFF.
     *
     * @see https://encoding.spec.whatwg.org/#utf-8-encoder
     */
    public static function encode(int $codePoint): string
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

    /**
     * Takes a UTF-8 encoded string and converts it into a series of integer code points. Any
     * invalid byte sequences will be replaced by a U+FFFD replacement code point.
     *
     * @see https://encoding.spec.whatwg.org/#utf-8-decoder
     *
     * @return list<int>
     */
    public static function utf8Decode(string $input): array
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
                if ($byte >= 0x00 && $byte <= 0x7F) {
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
