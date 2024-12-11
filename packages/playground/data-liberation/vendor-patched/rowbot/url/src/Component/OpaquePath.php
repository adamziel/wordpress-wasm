<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use Rowbot\URL\Exception\UnsupportedOperationException;

use function implode;

class OpaquePath extends AbstractPath
{
    public function __construct(PathSegment $path)
    {
        parent::__construct([$path]);
    }

    public function isOpaque(): bool
    {
        return true;
    }

    public function push(PathSegment $path): void
    {
        throw new UnsupportedOperationException('An opaque path can only contain a single path');
    }

    public function shorten(Scheme $scheme): void
    {
        throw new UnsupportedOperationException('Cannot shorten an opaque path');
    }

    /**
     * @see https://url.spec.whatwg.org/#url-path-serializer
     */
    public function __toString(): string
    {
        return implode('', $this->list);
    }
}
