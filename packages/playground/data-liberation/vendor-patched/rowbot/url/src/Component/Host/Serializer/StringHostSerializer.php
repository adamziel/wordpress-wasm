<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Serializer;

class StringHostSerializer implements HostSerializerInterface
{
    /**
     * @var string
     */
    private $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function toFormattedString(): string
    {
        return $this->string;
    }

    public function toString(): string
    {
        return $this->string;
    }
}
