<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use SeekableIterator;

/**
 * @extends \SeekableIterator<int, string>
 */
interface StringIteratorInterface extends SeekableIterator
{
    public function current(): string;

    public function key(): int;

    public function next(): void;

    public function peek(int $count = 1): string;

    public function prev(): void;

    /**
     * @param int $position The position to seek to relative to the current position.
     */

    public function seek($position): void;

    public function rewind(): void;

    public function valid(): bool;
}
