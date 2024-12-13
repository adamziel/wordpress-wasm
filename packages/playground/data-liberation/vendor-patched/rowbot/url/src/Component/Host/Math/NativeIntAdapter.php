<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Math;

use Rowbot\URL\Component\Host\Math\Exception\MathException;
use Stringable;

use function floor;
use function intval;

class NativeIntAdapter implements NumberInterface
{
    /**
     * @var int
     */
    private $number;

    /**
     * @param int|string $number
     */
    public function __construct($number, int $base = 10)
    {
        $this->number = intval($number, $base);
    }

    public function intdiv(int $number): NumberInterface
    {
        return new self((int) floor($this->number / $number));
    }

    /**
     * @param \Rowbot\URL\Component\Host\Math\NumberInterface $number
     */
    public function isEqualTo($number): bool
    {
        if (!$number instanceof self) {
            throw new MathException('Must be given an instance of itself.');
        }

        return $this->number === $number->number;
    }

    public function isGreaterThan(int $number): bool
    {
        return $this->number > $number;
    }

    /**
     * @param \Rowbot\URL\Component\Host\Math\NumberInterface $number
     */
    public function isGreaterThanOrEqualTo($number): bool
    {
        if (!$number instanceof self) {
            throw new MathException('Must be given an instance of itself.');
        }

        return $this->number >= $number->number;
    }

    public function mod(int $number): NumberInterface
    {
        return new self($this->number % $number);
    }

    public function multipliedBy(int $number): NumberInterface
    {
        return new self($this->number * $number);
    }

    /**
     * @param \Rowbot\URL\Component\Host\Math\NumberInterface $number
     */
    public function plus($number): NumberInterface
    {
        if (!$number instanceof self) {
            throw new MathException('Must be given an instance of itself.');
        }

        return new self($this->number + $number->number);
    }

    public function pow(int $number): NumberInterface
    {
        return new self($this->number ** $number);
    }

    /**
     * @return numeric-string
     */
    public function __toString(): string
    {
        return (string) $this->number;
    }
}
