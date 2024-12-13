<?php

declare(strict_types=1);

namespace Rowbot\URL;

use Stringable;

/**
 * @see https://html.spec.whatwg.org/multipage/browsers.html#origin
 */
interface Origin extends Stringable
{
    /**
     * @see https://html.spec.whatwg.org/multipage/browsers.html#concept-origin-effective-domain
     */
    public function getEffectiveDomain(): ?string;

    public function isOpaque(): bool;

    /**
     * Checks if two origins are the same.
     *
     * @see https://html.spec.whatwg.org/multipage/browsers.html#same-origin
     */
    public function isSameOrigin(self $other): bool;

    /**
     * Checks if the origin is both the same origin and the same domain.
     *
     * @see https://html.spec.whatwg.org/multipage/browsers.html#same-origin-domain
     */
    public function isSameOriginDomain(self $other): bool;
}
