<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use Rowbot\URL\Component\PathSegment;
use Rowbot\URL\Component\Scheme;

use function intval;
use function preg_match;

class StringBuffer extends AbstractStringBuffer implements StringBufferInterface
{
    public function isWindowsDriveLetter(): bool
    {
        return preg_match('/^[A-Za-z][:|]$/u', $this->string) === 1;
    }

    public function toInt(int $base = 10): int
    {
        return intval($this->string, $base);
    }

    public function toPath(): PathSegment
    {
        return new PathSegment($this->string);
    }

    public function toScheme(): Scheme
    {
        return new Scheme($this->string);
    }
}
