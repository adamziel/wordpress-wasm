<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests\Math;

use Rowbot\URL\Component\Host\Math\BrickMathAdapter;
use Rowbot\URL\Component\Host\Math\Exception\MathException;
use Rowbot\URL\Component\Host\Math\NativeIntAdapter;
use Rowbot\URL\Component\Host\Math\NumberInterface;

class BrickMathAdapterTest extends MathTestCase
{
    /**
     * @param int|string $number
     */
    public function createNumber($number, int $base = 10): NumberInterface
    {
        return new BrickMathAdapter($number, $base);
    }

    public function testIsEqaulToError(): void
    {
        $this->expectException(MathException::class);
        (new BrickMathAdapter(42))->isEqualTo(new NativeIntAdapter(42));
    }

    public function testIsGreaterThanOrEqualToError(): void
    {
        $this->expectException(MathException::class);
        (new BrickMathAdapter(42))->isGreaterThanOrEqualTo(new NativeIntAdapter(42));
    }

    public function testPlusError(): void
    {
        $this->expectException(MathException::class);
        (new BrickMathAdapter(42))->plus(new NativeIntAdapter(42));
    }
}
