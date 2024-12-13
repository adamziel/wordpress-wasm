<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Math;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Rowbot\URL\Component\Host\Math\Exception\MathException;
use Stringable;

use function assert;
use function is_numeric;
use function is_string;

class BrickMathAdapter implements NumberInterface
{
    /**
     * @var \Brick\Math\BigInteger
     */
    private $number;

    /**
     * @param int|string|\Brick\Math\BigInteger $number
     */
    public function __construct($number, int $base = 10)
    {
        if (is_string($number)) {
            $this->number = BigInteger::fromBase($number, $base);

            return;
        }

        $this->number = BigInteger::of($number);
    }

    public function intdiv(int $number): NumberInterface
    {
        return new self($this->number->dividedBy($number, RoundingMode::FLOOR));
    }

    /**
     * @param \Rowbot\URL\Component\Host\Math\NumberInterface $number
     */
    public function isEqualTo($number): bool
    {
        if (!$number instanceof self) {
            throw new MathException('Must be given an instance of itself.');
        }

        return $this->number->isEqualTo($number->number);
    }

    public function isGreaterThan(int $number): bool
    {
        return $this->number->isGreaterThan($number);
    }

    /**
     * @param \Rowbot\URL\Component\Host\Math\NumberInterface $number
     */
    public function isGreaterThanOrEqualTo($number): bool
    {
        if (!$number instanceof self) {
            throw new MathException('Must be given an instance of itself.');
        }

        return $this->number->isGreaterThanOrEqualTo($number->number);
    }

    public function mod(int $number): NumberInterface
    {
        return new self($this->number->mod($number));
    }

    public function multipliedBy(int $number): NumberInterface
    {
        return new self($this->number->multipliedBy($number));
    }

    /**
     * @param \Rowbot\URL\Component\Host\Math\NumberInterface $number
     */
    public function plus($number): NumberInterface
    {
        if (!$number instanceof self) {
            throw new MathException('Must be given an instance of itself.');
        }

        return new self($this->number->plus($number->number));
    }

    public function pow(int $number): NumberInterface
    {
        return new self($this->number->power($number));
    }

    /**
     * @return numeric-string
     */
    public function __toString(): string
    {
        $str = (string) $this->number;
        assert(is_numeric($str));

        return $str;
    }
}
