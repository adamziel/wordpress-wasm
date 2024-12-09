<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use IteratorAggregate;

/**
 * @extends \IteratorAggregate<int, string>
 */
interface USVStringInterface extends IteratorAggregate
{
    public function append(string $string): self;

    public function endsWith(string $string): bool;

    public function getIterator(): StringIteratorInterface;

    public function isEmpty(): bool;

    public function length(): int;

    /**
     * @param array<int, string>    $matches
     * @param int-mask<0, 256, 512> $flags
     */
    public function matches(string $pattern, ?array &$matches = null, int $flags = 0, int $offset = 0): bool;

    public function replaceRegex(
        string $pattern,
        string $replacement,
        int $limit = -1,
        int &$count = 0
    ): self;

    public function split(string $delimiter, ?int $limit = null): StringListInterface;

    public function startsWith(string $string): bool;

    public function startsWithTwoAsciiHexDigits(): bool;

    /**
     * @see https://url.spec.whatwg.org/#start-with-a-windows-drive-letter
     */
    public function startsWithWindowsDriveLetter(): bool;

    public function substr(int $start, ?int $length = null): self;

    public function toInt(int $base = 10): int;

    public function __toString(): string;
}
