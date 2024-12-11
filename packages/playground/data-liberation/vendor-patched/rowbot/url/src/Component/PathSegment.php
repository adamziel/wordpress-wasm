<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use Rowbot\URL\String\AbstractStringBuffer;
use Rowbot\URL\String\CodePoint;

use function rtrim;
use function strlen;
use function strpbrk;

/**
 * Represents a component in a URL's path as an ASCII string.
 */
class PathSegment extends AbstractStringBuffer
{
    /**
     * @see https://url.spec.whatwg.org/#normalized-windows-drive-letter
     */
    public function isNormalizedWindowsDriveLetter(): bool
    {
        return strlen($this->string) === 2
            && strpbrk($this->string[0], CodePoint::ASCII_ALPHA_MASK) === $this->string[0]
            && $this->string[1] === ':';
    }

    public function stripTrailingSpaces(): void
    {
        $this->string = rtrim($this->string, "\x20");
    }
}
