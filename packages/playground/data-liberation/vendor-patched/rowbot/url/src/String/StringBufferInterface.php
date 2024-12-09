<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use IteratorAggregate;
use Rowbot\URL\Component\PathSegment;
use Rowbot\URL\Component\Scheme;

/**
 * @extends \IteratorAggregate<int, string>
 */
interface StringBufferInterface extends IteratorAggregate
{
    public function append(string $string): void;

    public function clear(): void;

    public function isEmpty(): bool;

    /**
     * @see https://url.spec.whatwg.org/#windows-drive-letter
     */
    public function isWindowsDriveLetter(): bool;

    public function length(): int;

    public function prepend(string $string): void;

    public function setCodePointAt(int $index, string $codePoint): void;

    public function toInt(int $base = 10): int;

    public function toPath(): PathSegment;

    public function toScheme(): Scheme;

    public function toUtf8String(): USVStringInterface;

    public function __toString(): string;
}
