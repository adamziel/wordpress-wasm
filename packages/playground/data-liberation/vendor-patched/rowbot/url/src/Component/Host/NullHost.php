<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use Rowbot\URL\Component\Host\Serializer\HostSerializerInterface;
use Rowbot\URL\Component\Host\Serializer\StringHostSerializer;

class NullHost extends AbstractHost implements HostInterface
{
    /**
     * @param \Rowbot\URL\Component\Host\HostInterface $other
     */
    public function equals($other): bool
    {
        return $other instanceof self;
    }

    public function getSerializer(): HostSerializerInterface
    {
        return new StringHostSerializer('');
    }

    public function isNull(): bool
    {
        return true;
    }
}
