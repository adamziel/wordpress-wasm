<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use function in_array;
use function strpbrk;

/**
 * A helper class for working with UTF-8 code points.
 *
 * @see https://infra.spec.whatwg.org/#code-points
 */
final class CodePoint
{
    public const ASCII_ALPHA_MASK = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    public const ASCII_ALNUM_MASK = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    public const ASCII_DIGIT_MASK = '0123456789';
    public const OCTAL_DIGIT_MASK = '01234567';
    public const HEX_DIGIT_MASK = 'ABCDEFabcdef0123456789';

    public const EOF = '';

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * @see https://url.spec.whatwg.org/#url-code-points
     */
    public static function isUrlCodePoint(string $codePoint): bool
    {
        return (
            strpbrk($codePoint, self::ASCII_ALNUM_MASK) === $codePoint
            || in_array($codePoint, ['!', '$', ':', ';', '=', '?', '@', '_', '~'], true)
            || ($codePoint >= '&' && $codePoint <= '/')
            || ($codePoint >= "\xA0" && $codePoint <= "\u{10FFFD}")
        )

        // Not a surrogate
        && ($codePoint < "\u{D800}" || $codePoint > "\u{DFFF}")

        // Not a non-character
        && ($codePoint < "\u{FDD0}" || $codePoint > "\u{FDEF}")
        && !in_array($codePoint, [
            "\u{FFFE}",
            "\u{FFFF}",
            "\u{1FFFE}",
            "\u{1FFFF}",
            "\u{2FFFE}",
            "\u{2FFFF}",
            "\u{3FFFE}",
            "\u{3FFFF}",
            "\u{4FFFE}",
            "\u{4FFFF}",
            "\u{5FFFE}",
            "\u{5FFFF}",
            "\u{6FFFE}",
            "\u{6FFFF}",
            "\u{7FFFE}",
            "\u{7FFFF}",
            "\u{8FFFE}",
            "\u{8FFFF}",
            "\u{9FFFE}",
            "\u{9FFFF}",
            "\u{AFFFE}",
            "\u{AFFFF}",
            "\u{BFFFE}",
            "\u{BFFFF}",
            "\u{CFFFE}",
            "\u{CFFFF}",
            "\u{DFFFE}",
            "\u{DFFFF}",
            "\u{EFFFE}",
            "\u{EFFFF}",
            "\u{FFFFE}",
            "\u{FFFFF}",
            "\u{10FFFE}",
            "\u{10FFFF}",
        ], true);
    }
}
