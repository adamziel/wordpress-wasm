<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use Rowbot\URL\Component\Host\Serializer\HostSerializerInterface;
use Rowbot\URL\Component\Host\Serializer\IPv4AddressSerializer;

/**
 * @see https://url.spec.whatwg.org/#concept-ipv4
 */
class IPv4Address extends AbstractHost implements HostInterface
{
    /**
     * @var numeric-string
     */
    private $address;

    /**
     * @param numeric-string $address
     */
    public function __construct(string $address)
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
        return new IPv4AddressSerializer($this->address);
    }
}
