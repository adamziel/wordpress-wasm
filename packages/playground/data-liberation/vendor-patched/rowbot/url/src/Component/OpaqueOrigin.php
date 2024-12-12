<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use Rowbot\URL\Origin;

/**
 * @see https://html.spec.whatwg.org/multipage/browsers.html#concept-origin-opaque
 */
final class OpaqueOrigin implements Origin
{
    public function getEffectiveDomain(): ?string
    {
        return null;
    }

    public function isOpaque(): bool
    {
        return true;
    }

    /**
     * @param \Rowbot\URL\Origin $other
     */
    public function isSameOrigin($other): bool
    {
        return $this === $other;
    }

    /**
     * @param \Rowbot\URL\Origin $other
     */
    public function isSameOriginDomain($other): bool
    {
        return $this === $other;
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/origin.html#ascii-serialisation-of-an-origin
     */
    public function __toString(): string
    {
        return 'null';
    }
}
