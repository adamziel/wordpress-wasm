<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;
use Rowbot\URL\String\Utf8String;
use Rowbot\URL\Support\EncodingHelper;

use function array_column;
use function array_filter;
use function array_splice;
use function count;
use function explode;
use function mb_ord;
use function mb_str_split;
use function rawurldecode;
use function str_contains;
use function str_replace;
use function usort;

/**
 * @implements \IteratorAggregate<int, array{name: string, value: string}>
 */
class QueryList implements Countable, IteratorAggregate
{
    private const LEAD_OFFSET = 0xD800 - (0x10000 >> 10);

    /**
     * @var array<string, bool>
     */
    private $cache;

    /**
     * @var array<int, array{name: string, value: string}>
     */
    private $list;

    /**
     * @param array<int, array{name: string, value: string}> $list
     */
    public function __construct(array $list = [])
    {
        $this->list = $list;
        $this->cache = [];
    }

    /**
     * Decodes a application/x-www-form-urlencoded string and returns the decoded pairs as a list.
     *
     * Note: A legacy server-oriented implementation might have to support encodings other than
     * UTF-8 as well as have special logic for tuples of which the name is `_charset_`. Such logic
     * is not described here as only UTF-8' is conforming.
     *
     * @see https://url.spec.whatwg.org/#concept-urlencoded-parser
     */
    public static function fromString(string $input): self
    {
        // Let sequences be the result of splitting input on 0x26 (&).
        $sequences = explode('&', $input);

        // Let output be an initially empty list of name-value tuples where both name and value
        // hold a string.
        $output = new self();

        foreach ($sequences as $bytes) {
            if ($bytes === '') {
                continue;
            }

            // If bytes contains a 0x3D (=), then let name be the bytes from the start of bytes up
            // to but excluding its first 0x3D (=), and let value be the bytes, if any, after the
            // first 0x3D (=) up to the end of bytes. If 0x3D (=) is the first byte, then name will
            // be the empty byte sequence. If it is the last, then value will be the empty byte
            // sequence. Otherwise, let name have the value of bytes and let value be the empty byte
            // sequence.
            $name = $bytes;
            $value = '';

            if (strpos($bytes, '=') !== false) {
                [$name, $value] = explode('=', $bytes, 2);
            }

            // Replace any 0x2B (+) in name and value with 0x20 (SP).
            [$name, $value] = str_replace('+', "\x20", [$name, $value]);

            // Let nameString and valueString be the result of running UTF-8
            // decode without BOM on the percent decoding of name and value,
            // respectively.
            $output->append(
                Utf8String::transcode(rawurldecode($name), 'utf-8', 'utf-8'),
                Utf8String::transcode(rawurldecode($value), 'utf-8', 'utf-8')
            );
        }

        return $output;
    }

    /**
     * Appends a new name-value pair to the list.
     */
    public function append(string $name, string $value): void
    {
        $this->list[] = ['name' => $name, 'value' => $value];
        $this->cache[$name] = true;
    }

    public function count(): int
    {
        return count($this->list);
    }

    /**
     * Determines if a name-value pair with name $name exists in the collection.
     */
    public function contains(string $name, ?string $value = null): bool
    {
        $hasTuple = isset($this->cache[$name]);

        if ($value === null || !$hasTuple) {
            return $hasTuple;
        }

        foreach ($this->list as $tuple) {
            if ($name === $tuple['name'] && $value === $tuple['value']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a filtered array based on the given callback.
     *
     * @return array<int, array<string, string>>
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->list, $callback === null ? function ($value, $key) : bool {
            return !empty($value);
        } : $callback, $callback === null ? ARRAY_FILTER_USE_BOTH : 0);
    }

    /**
     * Returns the first name-value pair in the list whose name is $name.
     */
    public function first(string $name): ?string
    {
        foreach ($this->list as $pair) {
            if ($pair['name'] === $name) {
                return $pair['value'];
            }
        }

        return null;
    }

    /**
     * @return \ArrayIterator<int, array{name: string, value: string}>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->list);
    }

    /**
     * @return array{name: string, value: string}|null
     */
    public function getTupleAt(int $index): ?array
    {
        return $this->list[$index] ?? null;
    }

    /**
     * Removes all name-value pairs with name $name from the list.
     */
    public function remove(string $name, ?string $value): void
    {
        $seen = 0;
        $removed = 0;

        for ($i = count($this->list) - 1; $i >= 0; --$i) {
            if ($this->list[$i]['name'] === $name) {
                ++$seen;

                if ($value !== null && $this->list[$i]['value'] !== $value) {
                    continue;
                }

                array_splice($this->list, $i, 1);
                ++$removed;
            }
        }

        if ($seen === $removed) {
            unset($this->cache[$name]);
        }
    }

    /**
     * Sets the value of the first name-value pair with $name to $value and
     * removes all other occurances that have name $name.
     */
    public function set(string $name, string $value): void
    {
        $prevIndex = null;

        for ($i = count($this->list) - 1; $i >= 0; --$i) {
            if ($this->list[$i]['name'] === $name) {
                if ($prevIndex !== null) {
                    array_splice($this->list, $prevIndex, 1);
                }

                $prevIndex = $i;
            }
        }

        if ($prevIndex === null) {
            return;
        }

        $this->list[$prevIndex]['value'] = $value;
    }

    /**
     * Sorts the collection by code units and preserves the relative positioning
     * of name-value pairs.
     */
    public function sort(): void
    {
        $temp = [];

        foreach ($this->list as $pair) {
            $codeUnits = $this->convertToCodeUnits($pair['name']);
            $temp[] = ['original' => $pair, 'codeUnits' => $codeUnits, 'length' => count($codeUnits)];
        }

        // Sorting priority overview:
        //
        // Each string is compared code unit by code unit against each other.
        //
        // 1) If the two strings have different lengths, and the strings are equal up to the end of
        //    the shortest string, then the shorter of the two strings will be moved up in the
        //    array. (e.g. The string "aa" will come before the string "aaa".)
        // 2) If the value of the code units differ, the character with the lower code unit will be
        //    moved up in the array. (e.g. "🌈" will come before "ﬃ". Although "🌈" has a code
        //    point value of 127,752 that is greater than the "ﬃ" code point value of 64,259, "🌈"
        //    is split in to 2 code units and it's first code unit has a value of 55,356, which is
        //    less than the "ﬃ" single code unit value of 64,259.)
        // 3) If the two strings are considered equal, then they are sorted by the relative
        //    position in which they appeared in the array. (e.g. The string "b=c&a=c&b=a&a=a"
        //    becomes "a=c&a=a&b=c&b=a".)
        usort($temp, static function (array $a, array $b): int {
            $aCodeUnits = $a['codeUnits'];
            $bCodeUnits = $b['codeUnits'];
            $lengthComparison = $a['length'] <=> $b['length'];

            if ($lengthComparison === 0) {
                return $aCodeUnits <=> $bCodeUnits;
            }

            $shortestLength = $lengthComparison < 0 ? $a['length'] : $b['length'];

            for ($i = 0; $i < $shortestLength; ++$i) {
                $comparison = $aCodeUnits[$i] <=> $bCodeUnits[$i];

                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $lengthComparison;
        });

        $this->list = array_column($temp, 'original');
    }

    /**
     * Encodes the list of tuples as a valid application/x-www-form-urlencoded string.
     *
     * @see https://url.spec.whatwg.org/#concept-urlencoded-serializer
     */
    public function toUrlencodedString(?string $encodingOverride = null): string
    {
        $encoding = EncodingHelper::getOutputEncoding($encodingOverride) ?? 'utf-8';
        $output = '';
        $percentEncoder = new PercentEncoder();

        foreach ($this->list as $key => $tuple) {
            $name = $percentEncoder->percentEncodeAfterEncoding(
                $encoding,
                $tuple['name'],
                EncodeSet::X_WWW_URLENCODED,
                true
            );
            $value = $percentEncoder->percentEncodeAfterEncoding(
                $encoding,
                $tuple['value'],
                EncodeSet::X_WWW_URLENCODED,
                true
            );

            if ($key > 0) {
                $output .= '&';
            }

            $output .= $name . '=' . $value;
        }

        return $output;
    }

    /**
     * @see https://www.unicode.org/faq/utf_bom.html?source=post_page---------------------------#utf16-4
     *
     * @return list<non-empty-list<int>>
     */
    private function convertToCodeUnits(string $input): array
    {
        $codeUnits = [];

        foreach (mb_str_split($input, 1, 'utf-8') as $strCodePoint) {
            $codePoint = mb_ord($strCodePoint, 'utf-8');

            // Code points less than 0x10000 are part of the Basic Multilingual Plane and are
            // represented by a single code unit that is equal to its code point. Use 0 as the low
            // surrogate as the <=> operator compares array size first and values second.
            $codeUnits[] = $codePoint < 0x10000
                ? [$codePoint, 0]
                : [self::LEAD_OFFSET + ($codePoint >> 10), 0xDC00 + ($codePoint & 0x3FF)];
        }

        return $codeUnits;
    }
}
