<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function array_filter;
use function assert;
use function file_put_contents;
use function in_array;
use function is_array;
use function sprintf;

use const DIRECTORY_SEPARATOR as DS;

class RegexBuilder extends Builder
{
    public static function buildRegexClass(string $output): void
    {
        $bidiData = self::parseProperties('extracted/DerivedBidiClass.txt');
        $rtlLabel = sprintf(
            '/[%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return in_array($data[1], ['R', 'AL', 'AN'], true);
            }))
        );

        // Step 1. The first character must be a character with Bidi property L, R, or AL.  If it has the R
        // or AL property, it is an RTL label; if it has the L property, it is an LTR label.
        //
        // Because any code point not explicitly listed in DerivedBidiClass.txt is considered to have the
        // 'L' property, we negate a character class matching all code points explicitly listed in
        // DerivedBidiClass.txt minus the ones explicitly marked as 'L'.
        $bidiStep1Ltr = sprintf(
            '/^[^%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return $data[1] !== 'L';
            }))
        );
        $bidiStep1Rtl = sprintf(
            '/^[%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return in_array($data[1], ['R', 'AL'], true);
            }))
        );

        // Step 2. In an RTL label, only characters with the Bidi properties R, AL, AN, EN, ES, CS, ET, ON,
        // BN, or NSM are allowed.
        $bidiStep2 = sprintf(
            '/[^%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return in_array($data[1], ['R', 'AL', 'AN', 'EN', 'ES', 'CS', 'ET', 'ON', 'BN', 'NSM'], true);
            }))
        );

        // Step 3. In an RTL label, the end of the label must be a character with Bidi property R, AL, EN,
        // or AN, followed by zero or more characters with Bidi property NSM.
        $bidiStep3 = sprintf(
            '/[%s][%s]*$/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return in_array($data[1], ['R', 'AL', 'EN', 'AN'], true);
            })),
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return $data[1] === 'NSM';
            }))
        );

        // Step 4. In an RTL label, if an EN is present, no AN may be present, and vice versa.
        $bidiStep4EN = sprintf(
            '/[%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return $data[1] === 'EN';
            }))
        );
        $bidiStep4AN = sprintf(
            '/[%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return $data[1] === 'AN';
            }))
        );

        // Step 5. In an LTR label, only characters with the Bidi properties L, EN, ES, CS, ET, ON, BN, or
        // NSM are allowed.
        //
        // Because any code point not explicitly listed in DerivedBidiClass.txt is considered to have the
        // 'L' property, we create a character class matching all code points explicitly listed in
        // DerivedBidiClass.txt minus the ones explicitly marked as 'L', 'EN', 'ES', 'CS', 'ET', 'ON',
        // 'BN', or 'NSM'.
        $bidiStep5 = sprintf(
            '/[%s]/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return !in_array($data[1], ['L', 'EN', 'ES', 'CS', 'ET', 'ON', 'BN', 'NSM'], true);
            }))
        );

        // Step 6. In an LTR label, the end of the label must be a character with Bidi property L or EN,
        // followed by zero or more characters with Bidi property NSM.
        //
        // Again, because any code point not explicitly listed in DerivedBidiClass.txt is considered to
        // have the 'L' property, we negate a character class matching all code points explicitly listed in
        // DerivedBidiClass.txt to match characters with the 'L' and 'EN' property.
        $bidiStep6 = sprintf(
            '/[^%s][%s]*$/u',
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return !in_array($data[1], ['L', 'EN'], true);
            })),
            self::buildCharacterClass(array_filter($bidiData, static function (array $data): bool {
                return $data[1] === 'NSM';
            }))
        );

        $combiningMarks = self::buildCombiningMarksRegex();
        $zwnj = self::buildJoiningTypesRegex();
        $s = <<<RegexClass
<?php

// This file was auto generated by running 'php bin/generateDataFiles.php'

declare(strict_types=1);

namespace Rowbot\Idna\Resource;

final class Regex
{
    public const COMBINING_MARK = '{$combiningMarks}';

    public const RTL_LABEL = '{$rtlLabel}';

    public const BIDI_STEP_1_LTR = '{$bidiStep1Ltr}';
    public const BIDI_STEP_1_RTL = '{$bidiStep1Rtl}';
    public const BIDI_STEP_2 = '{$bidiStep2}';
    public const BIDI_STEP_3 = '{$bidiStep3}';
    public const BIDI_STEP_4_AN = '{$bidiStep4AN}';
    public const BIDI_STEP_4_EN = '{$bidiStep4EN}';
    public const BIDI_STEP_5 = '{$bidiStep5}';
    public const BIDI_STEP_6 = '{$bidiStep6}';

    public const ZWNJ = '{$zwnj}';

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
RegexClass;

        file_put_contents($output . DS . 'Regex.php', $s);
    }

    private static function buildJoiningTypesRegex(): string
    {
        $joiningTypes = self::parseProperties('extracted/DerivedJoiningType.txt');

        // ((Joining_Type:{L,D})(Joining_Type:T)*\u200C(Joining_Type:T)*(Joining_Type:{R,D}))
        // We use a capturing group around the first portion of the regex so we can count the byte length
        // of the match and increment preg_match's offset accordingly.
        return sprintf(
            '/([%1$s%2$s][%3$s]*\x{200C}[%3$s]*)[%4$s%2$s]/u',
            self::buildCharacterClass(array_filter($joiningTypes, static function (array $data): bool {
                return $data[1] === 'L';
            })),
            self::buildCharacterClass(array_filter($joiningTypes, static function (array $data): bool {
                return $data[1] === 'D';
            })),
            self::buildCharacterClass(array_filter($joiningTypes, static function (array $data): bool {
                return $data[1] === 'T';
            })),
            self::buildCharacterClass(array_filter($joiningTypes, static function (array $data): bool {
                return $data[1] === 'R';
            }))
        );
    }

    private static function buildCombiningMarksRegex(): string
    {
        $generalCategories = self::parseProperties('extracted/DerivedGeneralCategory.txt');

        return sprintf(
            '/^[%s]/u',
            self::buildCharacterClass(array_filter($generalCategories, static function (array $data): bool {
                return in_array($data[1], ['Mc', 'Me', 'Mn'], true);
            }))
        );
    }

    /**
     * @param array<int, array<int, array<int, int>|string>> $data
     */
    private static function buildCharacterClass(array $data): string
    {
        $out = '';

        foreach ($data as $codePoints) {
            assert(is_array($codePoints[0]));

            if ($codePoints[0][0] !== $codePoints[0][1]) {
                $out .= sprintf('\x{%04X}-\x{%04X}', ...$codePoints[0]);

                continue;
            }

            $out .= sprintf('\x{%04X}', $codePoints[0][0]);
        }

        return $out;
    }
}
