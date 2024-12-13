<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use Rowbot\URL\Component\Host\Serializer\HostSerializerInterface;

/**
 * @see https://url.spec.whatwg.org/#concept-host
 */
interface HostInterface
{
    /**
     * Checks if two hosts are equal.
     */
    public function equals(self $other): bool;

    /**
     * Returns a serializer for turning the underlying host into human readable text.
     */
    public function getSerializer(): HostSerializerInterface;

    /**
     * Checks if the host is an empty string.
     */
    public function isEmpty(): bool;

    /**
     * Checks if the host matches the string "localhost".
     */
    public function isLocalHost(): bool;

    /**
     * Checks if the implementor is considered a null host.
     */
    public function isNull(): bool;
}
