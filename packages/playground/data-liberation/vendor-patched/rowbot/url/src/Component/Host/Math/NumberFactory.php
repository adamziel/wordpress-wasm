<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Math;

use const PHP_INT_SIZE;

/**
 * Creates a number object based on the platform we are operating on. In the case of 32-bit PHP,
 * we must use a BigInt library since the stored representation of an IPv4 address can overflow
 * a 32-bit integer as it expects to be stored as an unsigned 32-bit integer, but PHP only
 * supports signed integers.
 */
final class NumberFactory
{
    /**
     * @param int|string $number
     */
    public static function createNumber($number, int $base): NumberInterface
    {
        // PHP_INT_SIZE returns the number of bytes that can fit in to an integer on the given
        // platform. If the size is 4, then we know we are operating on a 32-bit platform.
        if (PHP_INT_SIZE === 4) {
            return new BrickMathAdapter($number, $base);
        }

        return new NativeIntAdapter($number, $base);
    }
}
