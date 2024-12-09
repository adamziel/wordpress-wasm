<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host\Serializer;

use function dechex;

/**
 * @see https://url.spec.whatwg.org/#concept-ipv6-serializer
 */
class IPv6AddressSerializer implements HostSerializerInterface
{
    private const MAX_SIZE = 8;

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

    public function toFormattedString(): string
    {
        return '[' . $this->toString() . ']';
    }

    public function toString(): string
    {
        // 1. Let output be the empty string.
        $output = '';

        // 2. Let compress be an index to the first IPv6 piece in the first longest sequences of address’s IPv6 pieces
        // that are 0.
        // 3. If there is no sequence of address’s IPv6 pieces that are 0 that is longer than 1, then set compress to
        // null.
        // 4. Let ignore0 be false.
        [$compress, $longestSequence] = $this->getCompressLocation();
        $pieceIndex = 0;

        // 5. For each pieceIndex in the range 0 to 7, inclusive:
        do {
            // 5.3. If compress is pieceIndex, then:
            if ($compress === $pieceIndex) {
                // 5.3.1. Let separator be "::" if pieceIndex is 0, and U+003A (:) otherwise.
                // 5.3.2. Append separator to output.
                $output .= $pieceIndex === 0 ? '::' : ':';

                // 5.3.3. Set ignore0 to true and continue.
                //
                // Advance the pointer to $compress + $longestSequence
                // to skip over all 16-bit pieces that are 0 that immediately
                // follow the piece at $compress.
                $pieceIndex = $compress + $longestSequence;

                continue;
            }

            // 5.4. Append address[pieceIndex], represented as the shortest possible lowercase hexadecimal number, to
            // output.
            //
            // Is it safe to assume this always returns lowercase letters?
            $output .= dechex($this->address[$pieceIndex]);

            // 5.5. If pieceIndex is not 7, then append U+003A (:) to output.
            if ($pieceIndex < self::MAX_SIZE - 1) {
                $output .= ':';
            }

            ++$pieceIndex;
        } while ($pieceIndex < self::MAX_SIZE);

        // 6. Return output.
        return $output;
    }

    /**
     * Finds the longest sequence, with a length greater than 1, of 16-bit pieces that are 0 and
     * sets $compress to the first 16-bit piece in that sequence, otherwise $compress will remain
     * null.
     *
     * @return array{0: int|null, 1: int} The first item is the compress pointer, which indicates where in the address
     *                                    it can start compression, or null if the address isn't compressable. The
     *                                    second item is the length of the longest sequence of zeroes.
     */
    private function getCompressLocation(): array
    {
        $longestSequence = 1;
        $compress = null;
        $i = 0;

        do {
            if ($this->address[$i] !== 0) {
                continue;
            }

            $sequenceLength = 0;

            do {
                ++$sequenceLength;
                ++$i;
            } while ($i < self::MAX_SIZE && $this->address[$i] === 0);

            // We are only interested in sequences with a length greater than one. We also only want
            // to note the first of those sequences since there may be multiple sequences of zero
            // that have the same length.
            if ($sequenceLength > $longestSequence) {
                $longestSequence = $sequenceLength;
                $compress = $i - $sequenceLength;
            }
        } while (++$i < self::MAX_SIZE);

        return [$compress, $longestSequence];
    }
}
