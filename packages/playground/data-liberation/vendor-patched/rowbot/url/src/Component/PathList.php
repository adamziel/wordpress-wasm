<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use function array_pop;
use function count;
use function implode;

class PathList extends AbstractPath
{
    public function isOpaque(): bool
    {
        return false;
    }

    public function push(PathSegment $path): void
    {
        $this->list[] = $path;
    }

    public function shorten(Scheme $scheme): void
    {
        // 3. If url’s scheme is "file", path’s size is 1, and path[0] is a normalized Windows drive letter, then
        // return.
        if ($scheme->isFile() && count($this->list) === 1 && $this->list[0]->isNormalizedWindowsDriveLetter()) {
            return;
        }

        // 4. Remove path’s last item, if any.
        array_pop($this->list);
    }

    /**
     * @see https://url.spec.whatwg.org/#url-path-serializer
     */
    public function __toString(): string
    {
        if (!isset($this->list[0])) {
            return '';
        }

        return '/' . implode('/', $this->list);
    }
}
