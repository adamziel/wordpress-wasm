<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use Rowbot\URL\Component\Host\Serializer\HostSerializerInterface;
use Rowbot\URL\Component\Host\Serializer\IPv6AddressSerializer;

/**
 * @see https://url.spec.whatwg.org/#concept-ipv6
 */
class IPv6Address extends AbstractHost implements HostInterface
{
    /**
     * @var non-empty-list<int>
     */
    private $address;

    /**
     * @param non-empty-list<int> $address
     */
    public function __construct(array $address)
    {
        $this->address = $address;
    }

    /**
     * @param \Rowbot\URL\Component\Host\HostInterface $other
     */
    public function equals($other): bool
    {
        return $other instanceof self && $this->address === $other->address;
    }

    public function getSerializer(): HostSerializerInterface
    {
        return new IPv6AddressSerializer($this->address);
    }
}
