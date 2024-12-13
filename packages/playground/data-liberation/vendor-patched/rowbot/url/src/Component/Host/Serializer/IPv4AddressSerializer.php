<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Serializer;

use Rowbot\URL\Component\Host\Math\NumberFactory;

/**
 * @see https://url.spec.whatwg.org/#concept-ipv4-serializer
 */
class IPv4AddressSerializer implements HostSerializerInterface
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

    public function toFormattedString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        // 1. Let output be the empty string.
        $output = '';

        // 2. Let n be the value of address.
        $number = NumberFactory::createNumber($this->address, 10);

        // 3. For each i in the range 1 to 4, inclusive:
        for ($i = 0; $i < 4; ++$i) {
            // 3.1. Prepend n % 256, serialized, to output.
            $output = $number->mod(256) . $output;

            // 3.2. If i is not 4, then prepend U+002E (.) to output.
            if ($i < 3) {
                $output = '.' . $output;
            }

            // 3.3. Set n to floor(n / 256).
            $number = $number->intdiv(256);
        }

        // 4. Return output.
        return $output;
    }
}
