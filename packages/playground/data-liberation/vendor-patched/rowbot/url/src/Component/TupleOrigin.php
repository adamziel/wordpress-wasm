<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use Rowbot\URL\Component\Host\HostInterface;
use Rowbot\URL\Origin;

/**
 * @see https://html.spec.whatwg.org/multipage/origin.html#concept-origin-tuple
 */
final class TupleOrigin implements Origin
{
    /**
     * @var string|null
     */
    private $domain;

    /**
     * @var \Rowbot\URL\Component\Host\HostInterface
     */
    private $host;

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var string
     */
    private $scheme;

    public function __construct(string $scheme, HostInterface $host, ?int $port, ?string $domain = null)
    {
        $this->domain = $domain;
        $this->host = $host;
        $this->port = $port;
        $this->scheme = $scheme;
    }

    public function getEffectiveDomain(): ?string
    {
        if ($this->domain !== null) {
            return $this->domain;
        }

        return $this->host->getSerializer()->toFormattedString();
    }

    public function isOpaque(): bool
    {
        return false;
    }

    /**
     * @param \Rowbot\URL\Origin $other
     */
    public function isSameOrigin($other): bool
    {
        return $other instanceof self
            && $this->scheme === $other->scheme
            && $this->host->equals($other->host)
            && $this->port === $other->port;
    }

    /**
     * @param \Rowbot\URL\Origin $other
     */
    public function isSameOriginDomain($other): bool
    {
        // If A and B are both tuple origins...
        if ($other instanceof self) {
            // If A and B's schemes are identical, and their domains are
            // identical and non-null, then return true. Otherwise, if A and B
            // are same origin and their domains are identical and null, then
            // return true.
            if ($this->scheme === $other->scheme && $this->domain !== null && $this->domain === $other->domain) {
                return true;
            }

            if ($this->isSameOrigin($other) && $this->domain === null && $other->domain === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/origin.html#ascii-serialisation-of-an-origin
     */
    public function __toString(): string
    {
        $result = $this->scheme;
        $result .= '://';
        $result .= $this->host->getSerializer()->toFormattedString();

        if ($this->port !== null) {
            $result .= ':' . $this->port;
        }

        return $result;
    }
}
