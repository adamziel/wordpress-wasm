<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use Rowbot\Idna\Idna;
use RuntimeException;

use function array_map;
use function explode;
use function fclose;
use function fopen;
use function intval;
use function sprintf;
use function usort;

abstract class Builder
{
    protected const BASE_URL = 'https://www.unicode.org/Public';

    /**
     * @return resource
     */
    protected static function getIdnaDataResource(string $file)
    {
        $file = sprintf('%s/idna/%s/%s', self::BASE_URL, Idna::UNICODE_VERSION, $file);
        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new RuntimeException('Failed to open ' . $file);
        }

        return $handle;
    }

    /**
     * @return resource
     */
    protected static function getUnicodeDataResource(string $file)
    {
        $file = sprintf('%s/%s/ucd/%s', self::BASE_URL, Idna::UNICODE_VERSION, $file);
        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new RuntimeException('Failed to open ' . $file);
        }

        return $handle;
    }

    /**
     * @return list<int>
     */
    protected static function parseCodePoints(string $codePoints): array
    {
        $range = explode('..', $codePoints);
        $start = intval($range[0], 16);
        $end = isset($range[1]) ? intval($range[1], 16) : $start;

        return [$start, $end];
    }

    /**
     * @return array<int, array<int, array<int, int>|string>>
     */
    protected static function parseProperties(string $file): array
    {
        $handle = self::getUnicodeDataResource($file);
        $retVal = [];

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            $data[0] = self::parseCodePoints($data[0]);
            $retVal[] = $data;
        }

        fclose($handle);
        usort($retVal, static function (array $a, array $b): int {
            return $a[0][0] <=> $b[0][0];
        });

        return $retVal;
    }
}
