<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use Rowbot\URL\ParserContext;
use Rowbot\URL\String\CodePoint;
use Rowbot\URL\String\StringIteratorInterface;
use Rowbot\URL\String\USVStringInterface;

use function array_is_list;
use function assert;
use function intval;
use function strpbrk;
use function strpos;
use function strrpos;

/**
 * @see https://url.spec.whatwg.org/#concept-ipv6-parser
 */
class IPv6AddressParser
{
    /**
     * @return \Rowbot\URL\Component\Host\IPv6Address|false
     */
    public static function parse(ParserContext $context, USVStringInterface $input)
    {
        // 1. Let address be a new IPv6 address whose IPv6 pieces are all 0.
        $address = [0, 0, 0, 0, 0, 0, 0, 0];

        // 2. Let pieceIndex be 0.
        $pieceIndex = 0;

        // 3. Let compress be null.
        $compress = null;

        // 4. Let pointer be a pointer for input.
        $iter = $input->getIterator();
        $iter->rewind();

        // 5. If c is U+003A (:), then:
        if ($iter->current() === ':') {
            // 5.1. If remaining does not start with U+003A (:), validation error, return failure.
            if ($iter->peek() !== ':') {
                ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->warning('IPv6-invalid-compression', [
                    'input'  => (string) $input,
                    'column' => $iter->key() + 1,
                ]) : null;

                return false;
            }

            // 5.2. Increase pointer by 2.
            $iter->seek(2);

            // 5.3. Increase pieceIndex by 1 and then set compress to pieceIndex.
            $compress = ++$pieceIndex;
        }

        // 6. While c is not the EOF code point:
        while ($iter->valid()) {
            // 6.1. If pieceIndex is 8, validation error, return failure.
            if ($pieceIndex === 8) {
                ($nullsafeVariable2 = $context->logger) ? $nullsafeVariable2->warning('IPv6-too-many-pieces', [
                    'input'  => (string) $input,
                    'column' => $iter->key(),
                ]) : null;

                return false;
            }

            // 6.2. If c is U+003A (:), then:
            if ($iter->current() === ':') {
                // 6.2.1. If compress is non-null, validation error, return failure.
                if ($compress !== null) {
                    ($nullsafeVariable3 = $context->logger) ? $nullsafeVariable3->warning('IPv6-multiple-compression', [
                        'input'  => (string) $input,
                        'column' => $iter->key() + 1,
                    ]) : null;

                    return false;
                }

                // 6.2.2. Increase pointer and pieceIndex by 1, set compress to pieceIndex, and then continue.
                $iter->next();
                $compress = ++$pieceIndex;

                continue;
            }

            // 6.3. Let value and length be 0.
            $value = 0;
            $length = 0;
            $current = $iter->current();

            // 6.4. While length is less than 4 and c is an ASCII hex digit, set value to value × 0x10 + c interpreted
            // as hexadecimal number, and increase pointer and length by 1.
            while ($length < 4 && strpbrk($current, CodePoint::HEX_DIGIT_MASK) === $current) {
                $value = ($value * 0x10) + intval($current, 16);
                $iter->next();
                ++$length;
                $current = $iter->current();
            }

            // 6.5. If c is U+002E (.), then:
            if ($iter->current() === '.') {
                // 6.5.1. If length is 0, validation error, return failure.
                if ($length === 0) {
                    ($nullsafeVariable4 = $context->logger) ? $nullsafeVariable4->warning('IPv4-in-IPv6-invalid-code-point', [
                        'input'  => (string) $input,
                        'column' => $iter->key() + 1,
                    ]) : null;

                    return false;
                }

                // 6.5.2. Decrease pointer by length.
                $iter->seek(-$length);

                // 6.5.3. If pieceIndex is greater than 6, validation error, return failure.
                if ($pieceIndex > 6) {
                    ($nullsafeVariable5 = $context->logger) ? $nullsafeVariable5->warning('IPv4-in-IPv6-too-many-pieces', [
                        'input'  => (string) $input,
                        'column' => $iter->key(),
                    ]) : null;

                    return false;
                }

                $result = self::parseIPv4Address($context, $input, $iter, $address, $pieceIndex);

                if ($result === false) {
                    return false;
                }

                [$address, $pieceIndex] = $result;

                // 6.5.7. Break.
                break;
            }

            // 6.6. Otherwise, if c is U+003A (:):
            if ($iter->current() === ':') {
                // 6.6.1. Increase pointer by 1.
                $iter->next();

                // 6.2.2. If c is the EOF code point, validation error, return failure.
                if (!$iter->valid()) {
                    ($nullsafeVariable6 = $context->logger) ? $nullsafeVariable6->warning('IPv6-invalid-code-point', [
                        'input'  => (string) $input,
                        'column' => $iter->key() + 1,
                    ]) : null;

                    return false;
                }

            // 6.7. Otherwise, if c is not the EOF code point, validation error, return failure.
            } elseif ($iter->valid()) {
                ($nullsafeVariable7 = $context->logger) ? $nullsafeVariable7->warning('IPv6-invalid-code-point', [
                    'input'  => (string) $input,
                    'column' => $iter->key() + 1,
                ]) : null;

                return false;
            }

            // 6.8. Set address[pieceIndex] to value.
            // 6.9. Increase pieceIndex by 1.
            $address[$pieceIndex++] = $value;
        }

        // 7. If compress is non-null, then:
        if ($compress !== null) {
            // 7.1. Let swaps be pieceIndex − compress.
            $swaps = $pieceIndex - $compress;

            // 7.2. Set pieceIndex to 7.
            $pieceIndex = 7;

            // 7.3. While pieceIndex is not 0 and swaps is greater than 0, swap address[pieceIndex] with
            // address[compress + swaps − 1], and then decrease both pieceIndex and swaps by 1.
            while ($pieceIndex !== 0 && $swaps > 0) {
                $temp = $address[$pieceIndex];
                $address[$pieceIndex] = $address[$compress + $swaps - 1];
                $address[$compress + $swaps - 1] = $temp;
                --$pieceIndex;
                --$swaps;
            }

        // Otherwise, if compress is null and pieceIndex is not 8, validation error, return failure.
        } elseif ($pieceIndex !== 8) {
            ($nullsafeVariable8 = $context->logger) ? $nullsafeVariable8->warning('IPv6-too-few-pieces', [
                'input'  => (string) $input,
                'column' => $iter->key() + 1,
            ]) : null;

            return false;
        }
        $arrayIsListFunction = function (array $array) : bool {
            if (function_exists('array_is_list')) {
                return array_is_list($array);
            }
            if ($array === []) {
                return true;
            }
            $current_key = 0;
            foreach ($array as $key => $noop) {
                if ($key !== $current_key) {
                    return false;
                }
                ++$current_key;
            }
            return true;
        };

        assert($arrayIsListFunction($address));

        // 9. Return address.
        return new IPv6Address($address);
    }

    /**
     * @param non-empty-list<int> $address
     *
     * @return array{0: non-empty-list<int>, 1: int}|false
     */
    private static function parseIPv4Address(
        ParserContext $context,
        USVStringInterface $input,
        StringIteratorInterface $iter,
        array $address,
        int $pieceIndex
    ) {
        // 6.5.4. Let numbersSeen be 0.
        $numbersSeen = 0;

        // 6.5.5. While c is not the EOF code point:
        do {
            // 6.5.5.1. Let ipv4Piece be null.
            $ipv4Piece = null;

            // 6.5.5.2. If numbersSeen is greater than 0, then:
            if ($numbersSeen > 0) {
                // 6.5.5.2.2 Otherwise, validation error, return failure.
                if ($iter->current() !== '.' || $numbersSeen >= 4) {
                    // Validation error.
                    ($nullsafeVariable9 = $context->logger) ? $nullsafeVariable9->warning('IPv4-in-IPv6-invalid-code-point', [
                        'input'  => (string) $input,
                        'column' => $iter->key() + 1,
                    ]) : null;

                    return false;
                }

                // 6.5.5.2.1 If c is a U+002E (.) and numbersSeen is less than 4, then increase pointer by 1.
                $iter->next();
            }

            $current = $iter->current();

            // 6.5.5.3. If c is not an ASCII digit, validation error, return failure.
            if (strpbrk($current, CodePoint::ASCII_DIGIT_MASK) !== $current) {
                // Validation error.
                ($nullsafeVariable10 = $context->logger) ? $nullsafeVariable10->warning('IPv4-in-IPv6-invalid-code-point', [
                    'input'  => (string) $input,
                    'column' => $iter->key() + 1,
                ]) : null;

                return false;
            }

            // 6.5.5.4. While c is an ASCII digit:
            do {
                // 6.5.5.4.1. Let number be c interpreted as decimal number.
                $number = (int) $current;

                // 6.5.5.4.2. If ipv4Piece is null, then set ipv4Piece to number.
                if ($ipv4Piece === null) {
                    $ipv4Piece = $number;

                // Otherwise, if ipv4Piece is 0, validation error, return failure.
                } elseif ($ipv4Piece === 0) {
                    // Validation error.
                    ($nullsafeVariable11 = $context->logger) ? $nullsafeVariable11->warning('IPv4-in-IPv6-invalid-code-point', [
                        'input'  => (string) $input,
                        'column' => $iter->key(),
                    ]) : null;

                    return false;

                // Otherwise, set ipv4Piece to ipv4Piece × 10 + number.
                } else {
                    $ipv4Piece = ($ipv4Piece * 10) + $number;
                }

                // 6.5.5.4.3. If ipv4Piece is greater than 255, validation error, return failure.
                if ($ipv4Piece > 255) {
                    // Validation error.
                    ($nullsafeVariable12 = $context->logger) ? $nullsafeVariable12->warning('IPv4-in-IPv6-out-of-range-part', [
                        'input'  => (string) $input,
                        'column_range' => (static function () use ($input, $iter, $numbersSeen): array {
                            $str = (string) $input;
                            $delimiter = $numbersSeen === 0 ? ':' : '.';
                            $startIndex = strrpos($str, $delimiter, -($input->length() - $iter->key()));
                            $endIndex = strpos($str, '.', $iter->key());

                            if ($startIndex === false) {
                                $startIndex = $iter->key();
                            }

                            if ($endIndex === false) {
                                $endIndex = $input->length();
                            }

                            return [$startIndex + 2, $endIndex];
                        })(),
                    ]) : null;

                    return false;
                }

                // 6.5.5.4.4. Increase pointer by 1.
                $iter->next();
                $current = $iter->current();
            } while (strpbrk($current, CodePoint::ASCII_DIGIT_MASK) === $current);

            // 6.5.5.5. Set address[pieceIndex] to address[pieceIndex] × 0x100 + ipv4Piece.
            $piece = $address[$pieceIndex];
            $address[$pieceIndex] = ($piece * 0x100) + $ipv4Piece;

            // 6.5.5.6. Increase numbersSeen by 1.
            ++$numbersSeen;

            // 6.5.5.7. If numbersSeen is 2 or 4, then increase pieceIndex by 1.
            if ($numbersSeen === 2 || $numbersSeen === 4) {
                ++$pieceIndex;
            }
        } while ($iter->valid());

        // 6.5.6. If numbersSeen is not 4, validation error, return failure.
        if ($numbersSeen !== 4) {
            // Validation error.
            ($nullsafeVariable13 = $context->logger) ? $nullsafeVariable13->warning('IPv4-in-IPv6-too-few-parts', [
                'input'  => (string) $input,
                'column' => $iter->key() + 1,
            ]) : null;

            return false;
        }
        $arrayIsListFunction = function (array $array) : bool {
            if (function_exists('array_is_list')) {
                return array_is_list($array);
            }
            if ($array === []) {
                return true;
            }
            $current_key = 0;
            foreach ($array as $key => $noop) {
                if ($key !== $current_key) {
                    return false;
                }
                ++$current_key;
            }
            return true;
        };

        assert($arrayIsListFunction($address));

        return [$address, $pieceIndex];
    }
}
