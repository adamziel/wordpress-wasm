<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use IteratorAggregate;
use Stringable;

use function mb_strlen;
use function mb_substr;

/**
 * @implements \IteratorAggregate<int, string>
 */
abstract class AbstractStringBuffer implements IteratorAggregate
{
    /**
     * @var string
     */
    protected $string;

    public function __construct(string $string = '')
    {
        $this->string = $string;
    }

    public function append(string $string): void
    {
        $this->string .= $string;
    }

    public function clear(): void
    {
        $this->string = '';
    }

    public function getIterator(): StringIteratorInterface
    {
        return new Utf8StringIterator($this->string);
    }

    public function isEmpty(): bool
    {
        return $this->string === '';
    }

    public function length(): int
    {
        return mb_strlen($this->string, 'utf-8');
    }

    public function prepend(string $string): void
    {
        $this->string = $string . $this->string;
    }

    public function setCodePointAt(int $index, string $codePoint): void
    {
        $prefix = mb_substr($this->string, 0, $index, 'utf-8');
        $suffix = mb_substr($this->string, $index + 1, null, 'utf-8');
        $this->string = $prefix . $codePoint . $suffix;
    }

    public function toUtf8String(): USVStringInterface
    {
        return new Utf8String($this->string);
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
