<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use Rowbot\URL\Component\Host\Serializer\HostSerializerInterface;
use Rowbot\URL\Component\Host\Serializer\StringHostSerializer;
use Rowbot\URL\String\Utf8String;

/**
 * @see https://url.spec.whatwg.org/#concept-domain
 * @see https://url.spec.whatwg.org/#opaque-host
 * @see https://url.spec.whatwg.org/#empty-host
 */
class StringHost extends Utf8String implements HostInterface
{
    /**
     * @param \Rowbot\URL\Component\Host\HostInterface $other
     */
    public function equals($other): bool
    {
        return $other instanceof self && $this->string === $other->string;
    }

    public function getSerializer(): HostSerializerInterface
    {
        return new StringHostSerializer($this->string);
    }

    public function isLocalHost(): bool
    {
        return $this->string === 'localhost';
    }

    public function isNull(): bool
    {
        return false;
    }
}
