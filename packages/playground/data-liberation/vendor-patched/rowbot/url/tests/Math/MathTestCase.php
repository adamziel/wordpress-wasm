<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests\Math;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\Component\Host\Math\NumberInterface;

use const PHP_INT_MAX;

abstract class MathTestCase extends TestCase
{
    /**
     * @param int|string $number
     */
    abstract public function createNumber($number, int $base = 10): NumberInterface;

    public function testIntDiv(int $divisor, string $quoient): void
    {
        $dividend = $this->createNumber(42);
        $computedQuotient = $dividend->intdiv($divisor);
        self::assertTrue($computedQuotient->isEqualTo($this->createNumber($quoient)));
        self::assertSame($quoient, (string) $computedQuotient);
    }

    public static function equalityNumberProvider(): array
    {
        return [
            [PHP_INT_MAX, 10, (string) PHP_INT_MAX],
            ['01234567', 8, '342391'],
            ['DF', 16, '223'],
            ['-24', 10, '-24'],
        ];
    }

    /**
     * @param int|string $number
     */
    public function testIsEqualTo($number, int $base, string $expected): void
    {
        self::assertTrue($this->createNumber($number, $base)->isEqualTo($this->createNumber($expected)));
    }

    public function testIsGreaterThan(int $number1, int $number2, bool $result): void
    {
        self::assertSame($result, $this->createNumber($number1)->isGreaterThan($number2));
    }

    public function testIsGreaterThanOrEqualTo(int $number1, int $number2, bool $result): void
    {
        self::assertSame($result, $this->createNumber($number1)->isGreaterThanOrEqualTo($this->createNumber($number2)));
    }

    public function testMod(int $dividend, int $divisor, int $remainder): void
    {
        $computedRemainder = $this->createNumber($dividend)->mod($divisor);
        self::assertTrue($computedRemainder->isEqualTo($this->createNumber($remainder)));
        self::assertSame((string) $remainder, (string) $computedRemainder);
    }

    public function testMultipliedBy(int $multiplicand, int $multiplier, int $product): void
    {
        $computedProduct = $this->createNumber($multiplicand)->multipliedBy($multiplier);
        self::assertTrue($computedProduct->isEqualTo($this->createNumber($product)));
        self::assertSame((string) $product, (string) $computedProduct);
    }

    public function testPlus(int $addend1, int $addend2, string $sum): void
    {
        $computedSum = $this->createNumber($addend1)->plus($this->createNumber($addend2));
        self::assertTrue($computedSum->isEqualTo($this->createNumber($sum)));
        self::assertSame($sum, (string) $computedSum);
    }

    public function testPow(int $base, int $exponent, string $power): void
    {
        $computedPower = $this->createNumber($base)->pow($exponent);
        self::assertTrue($computedPower->isEqualTo($this->createNumber($power)));
        self::assertSame($power, (string) $computedPower);
    }

    /**
     * @param int|string $number
     */
    public function testToString($number, int $base, string $result): void
    {
        self::assertSame($result, (string) $this->createNumber($number, $base));
    }
}
