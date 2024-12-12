<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use Generator;
use Rowbot\URL\String\Exception\UndefinedIndexException;

use function array_pop;
use function count;

/**
 * @template T of object
 */
abstract class AbstractStringList
{
    /**
     * @var array<int, T>
     */
    protected $list;

    /**
     * @param array<int, T> $list
     */
    public function __construct(array $list = [])
    {
        $this->list = $list;
    }

    public function count(): int
    {
        return count($this->list);
    }

    /**
     * @return T
     */
    public function first()
    {
        if (!isset($this->list[0])) {
            throw new UndefinedIndexException();
        }

        return $this->list[0];
    }

    public function isEmpty(): bool
    {
        return $this->list === [];
    }

    /**
     * @return T
     */
    public function last()
    {
        $last = count($this->list) - 1;

        if ($last < 0) {
            throw new UndefinedIndexException();
        }

        return $this->list[$last];
    }

    /**
     * @return T|null
     */
    public function pop()
    {
        return array_pop($this->list);
    }

    /**
     * @param T $string
     */
    public function push($string): void
    {
        $this->list[] = $string;
    }

    /**
     * @return \Generator<int, T>
     */
    public function getIterator(): Generator
    {
        foreach ($this->list as $string) {
            yield $string;
        }
    }

    public function __clone()
    {
        $temp = [];

        foreach ($this->list as $string) {
            $temp[] = clone $string;
        }

        $this->list = $temp;
    }
}
