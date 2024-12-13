<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Math;

interface NumberInterface
{
    /**
     * Performs integer division, flooring the result.
     */
    public function intdiv(int $number): self;

    /**
     * @throws \Rowbot\URL\Component\Host\Math\Exception\MathException If anything other than the
     *                                                                 instance is given.
     */
    public function isEqualTo(self $number): bool;

    public function isGreaterThan(int $number): bool;

    /**
     * @throws \Rowbot\URL\Component\Host\Math\Exception\MathException If anything other than the
     *                                                                 instance is given.
     */
    public function isGreaterThanOrEqualTo(self $number): bool;

    public function mod(int $number): self;

    public function multipliedBy(int $number): self;

    /**
     * @throws \Rowbot\URL\Component\Host\Math\Exception\MathException If anything other than the
     *                                                                 instance is given.
     */
    public function plus(self $number): self;

    public function pow(int $number): self;

    /**
     * @return numeric-string
     */
    public function __toString(): string;
}
