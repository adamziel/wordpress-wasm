<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use Rowbot\URL\String\Exception\UndefinedIndexException;
use Rowbot\URL\URLRecord;

use function count;

abstract class AbstractPath implements PathInterface
{
    /**
     * @var list<\Rowbot\URL\Component\PathSegment>
     */
    protected $list;

    /**
     * @param list<\Rowbot\URL\Component\PathSegment> $paths
     */
    public function __construct(array $paths = [])
    {
        $this->list = $paths;
    }

    public function count(): int
    {
        return count($this->list);
    }

    public function first(): PathSegment
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

    public function potentiallyStripTrailingSpaces(URLRecord $url): void
    {
        if (!$this->isOpaque()) {
            return;
        }

        if ($url->fragment !== null) {
            return;
        }

        if ($url->query !== null) {
            return;
        }

        $this->list[0]->stripTrailingSpaces();
    }

    public function __clone()
    {
        $list = [];

        foreach ($this->list as $path) {
            $list[] = clone $path;
        }

        $this->list = $list;
    }
}
