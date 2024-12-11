<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use function mb_str_split;

class Utf8StringIterator implements StringIteratorInterface
{
    /**
     * @var list<string>
     */
    private $codePoints;

    /**
     * @var int
     */
    private $cursor;

    public function __construct(string $string)
    {
        $this->codePoints = mb_str_split($string, 1, 'utf-8');
        $this->cursor = 0;
    }

    public function current(): string
    {
        return $this->codePoints[$this->cursor] ?? '';
    }

    public function key(): int
    {
        return $this->cursor;
    }

    public function next(): void
    {
        ++$this->cursor;
    }

    public function peek(int $count = 1): string
    {
        if ($count === 1) {
            return $this->codePoints[$this->cursor + 1] ?? '';
        }

        $output = '';
        $cursor = $this->cursor + 1;

        for ($i = 0; $i < $count; ++$i) {
            if (!isset($this->codePoints[$cursor])) {
                break;
            }

            $output .= $this->codePoints[$cursor];
            ++$cursor;
        }

        return $output;
    }

    public function prev(): void
    {
        --$this->cursor;
    }

    public function rewind(): void
    {
        $this->cursor = 0;
    }

    public function seek($position): void
    {
        $this->cursor += $position;
    }

    public function valid(): bool
    {
        return $this->cursor > -1 && isset($this->codePoints[$this->cursor]);
    }
}
