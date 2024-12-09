<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function array_map;
use function explode;
use function fclose;
use function fgets;
use function file_put_contents;
use function var_export;

use const DIRECTORY_SEPARATOR as DS;

class ViramaDataBuilder extends Builder
{
    public static function buildHashMap(string $output): void
    {
        $handle = self::getUnicodeDataResource('extracted/DerivedCombiningClass.txt');
        $virama = [];

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            [$codePoints, $combiningClass] = $data;

            if ($combiningClass !== '9') {
                continue;
            }

            $codePoints = self::parseCodePoints($codePoints);
            $diff = $codePoints[1] - $codePoints[0] + 1;

            for ($i = 0; $i < $diff; ++$i) {
                $virama[$codePoints[0] + $i] = (int) $combiningClass;
            }
        }

        fclose($handle);
        file_put_contents($output . DS . 'virama.php', "<?php\n\nreturn " . var_export($virama, true) . ";\n");
    }
}
