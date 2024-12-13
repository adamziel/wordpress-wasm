<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use Rowbot\Idna\CodePoint;
use RuntimeException;

use function array_map;
use function explode;
use function fclose;
use function fgets;
use function file_put_contents;
use function intval;
use function preg_match_all;

use const DIRECTORY_SEPARATOR as DS;

class IdnaDataBuilder extends Builder
{
    public static function buildHashMaps(string $output): void
    {
        $handle = self::getIdnaDataResource('IdnaMappingTable.txt');
        $statuses = [
            'mapped'                 => [],
            'ignored'                => [],
            'deviation'              => [],
            'disallowed'             => [],
            'disallowed_STD3_mapped' => [],
            'disallowed_STD3_valid'  => [],
        ];
        $rangeFallback = '';

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            [$codePoints, $status] = $data;
            $codePoints = self::parseCodePoints($codePoints);
            $diff = $codePoints[1] - $codePoints[0] + 1;

            switch ($status) {
                case 'valid':
                    // skip valid.
                    break;

                case 'mapped':
                case 'deviation':
                case 'disallowed_STD3_mapped':
                    if (preg_match_all('/[[:xdigit:]]+/', $data[2], $matches) === false) {
                        throw new RuntimeException();
                    }

                    $mapped = '';

                    foreach ($matches[0] as $codePoint) {
                        $mapped .= CodePoint::encode(intval($codePoint, 16));
                    }

                    for ($i = 0; $i < $diff; ++$i) {
                        $statuses[$status][$codePoints[0] + $i] = $mapped;
                    }

                    break;

                case 'disallowed':
                    if ($diff > 30) {
                        if ($rangeFallback !== '') {
                            $rangeFallback .= "\n\n";
                        }

                        $rangeFallback .= <<<RANGE_FALLBACK
        if (\$codePoint >= {$codePoints[0]} && \$codePoint <= {$codePoints[1]}) {
            return true;
        }
RANGE_FALLBACK;

                        continue 2;
                    }

                    for ($i = 0; $i < $diff; ++$i) {
                        $statuses[$status][$codePoints[0] + $i] = true;
                    }

                    break;

                case 'ignored':
                case 'disallowed_STD3_valid':
                    for ($i = 0; $i < $diff; ++$i) {
                        $statuses[$status][$codePoints[0] + $i] = true;
                    }

                    break;
            }
        }

        fclose($handle);
        file_put_contents($output . DS . 'mapped.php', "<?php\n\nreturn " . var_export($statuses['mapped'], true) . ";\n");
        file_put_contents($output . DS . 'ignored.php', "<?php\n\nreturn " . var_export($statuses['ignored'], true) . ";\n");
        file_put_contents($output . DS . 'deviation.php', "<?php\n\nreturn " . var_export($statuses['deviation'], true) . ";\n");
        file_put_contents($output . DS . 'disallowed.php', "<?php\n\nreturn " . var_export($statuses['disallowed'], true) . ";\n");
        file_put_contents($output . DS . 'disallowed_STD3_mapped.php', "<?php\n\nreturn " . var_export($statuses['disallowed_STD3_mapped'], true) . ";\n");
        file_put_contents($output . DS . 'disallowed_STD3_valid.php', "<?php\n\nreturn " . var_export($statuses['disallowed_STD3_valid'], true) . ";\n");
        $s = <<<CP_STATUS
<?php

declare(strict_types=1);

namespace Rowbot\Idna\Resource;

final class DisallowedRanges
{
    public static function inRange(int \$codePoint): bool
    {
{$rangeFallback}

        return false;
    }
}

CP_STATUS;

        file_put_contents($output . DS . 'DisallowedRanges.php', $s);
    }
}
