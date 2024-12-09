<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Idna\Resource\DisallowedRanges;

use const DIRECTORY_SEPARATOR as DS;

final class CodePointStatus
{
    private const RESOURCE_DIR = __DIR__ . DS . '..' . DS . 'resources' . DS;

    /**
     * @var array<int, string>
     */
    private static $mapped;

    /**
     * @var array<int, bool>
     */
    private static $ignored;

    /**
     * @var array<int, string>
     */
    private static $deviation;

    /**
     * @var array<int, bool>
     */
    private static $disallowed;

    /**
     * @var array<int, string>
     */
    private static $disallowed_STD3_mapped;

    /**
     * @var array<int, bool>
     */
    private static $disallowed_STD3_valid;

    /**
     * @var bool
     */
    private static $dataLoaded = false;

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * @return array{status: string, mapping?: string}
     */
    public static function lookup(int $codePoint, bool $useSTD3ASCIIRules): array
    {
        if (!self::$dataLoaded) {
            self::$dataLoaded = true;
            self::$mapped = require self::RESOURCE_DIR . 'mapped.php';
            self::$ignored = require self::RESOURCE_DIR . 'ignored.php';
            self::$deviation = require self::RESOURCE_DIR . 'deviation.php';
            self::$disallowed = require self::RESOURCE_DIR . 'disallowed.php';
            self::$disallowed_STD3_mapped = require self::RESOURCE_DIR . 'disallowed_STD3_mapped.php';
            self::$disallowed_STD3_valid = require self::RESOURCE_DIR . 'disallowed_STD3_valid.php';
        }

        if (isset(self::$mapped[$codePoint])) {
            return ['status' => 'mapped', 'mapping' => self::$mapped[$codePoint]];
        }

        if (isset(self::$ignored[$codePoint])) {
            return ['status' => 'ignored'];
        }

        if (isset(self::$deviation[$codePoint])) {
            return ['status' => 'deviation', 'mapping' => self::$deviation[$codePoint]];
        }

        if (isset(self::$disallowed[$codePoint]) || DisallowedRanges::inRange($codePoint)) {
            return ['status' => 'disallowed'];
        }

        $isDisallowedMapped = isset(self::$disallowed_STD3_mapped[$codePoint]);

        if ($isDisallowedMapped || isset(self::$disallowed_STD3_valid[$codePoint])) {
            $status = 'disallowed';

            if (!$useSTD3ASCIIRules) {
                $status = $isDisallowedMapped ? 'mapped' : 'valid';
            }

            if ($isDisallowedMapped) {
                return ['status' => $status, 'mapping' => self::$disallowed_STD3_mapped[$codePoint]];
            }

            return ['status' => $status];
        }

        return ['status' => 'valid'];
    }
}
