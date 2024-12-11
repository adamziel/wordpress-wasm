<?php

declare(strict_types=1);

namespace Rowbot\Idna\Test;

use ReflectionClass;
use Rowbot\Idna\Idna;

use function array_filter;
use function array_flip;
use function is_array;
use function sprintf;
use function strncmp;

use const ARRAY_FILTER_USE_KEY;

class IdnaV2Test extends IdnaV2TestCase
{
    private const DEFAULT_OPTIONS = [
        'CheckHyphens'            => true,
        'CheckBidi'               => true,
        'CheckJoiners'            => true,
        'UseSTD3ASCIIRules'       => true,
        'Transitional_Processing' => false,
        'VerifyDnsLength'         => true,
    ];

    /**
     * @var array<int, string>
     */
    protected static $errorMap;

    public static function setUpBeforeClass(): void
    {
        if (isset(self::$errorMap)) {
            return;
        }

        $reflection = new ReflectionClass(Idna::class);
        $errors = array_filter($reflection->getConstants(), static function (string $name): bool {
            return strncmp($name, 'ERROR_', 6) === 0;
        }, ARRAY_FILTER_USE_KEY);
        self::$errorMap = array_flip($errors);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function getData(): array
    {
        return $this->loadTestData(Idna::UNICODE_VERSION);
    }

    /**
     * @dataProvider getData
     */
    public function testToUnicode(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus
    ): void {
        [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ] = $this->translate($source, $toUnicode, $toUnicodeStatus, $toAsciiN, $toAsciiNStatus, $toAsciiT, $toAsciiTStatus);

        $result = Idna::toUnicode($source, self::DEFAULT_OPTIONS);
        self::assertSame($toUnicode, $result->getDomain());

        if ($toUnicodeStatus === []) {
            self::assertFalse($result->hasErrors(), sprintf(
                'Expected no errors, but found %s.',
                $this->containedErrors($result->getErrors())
            ));
        } else {
            self::assertTrue($result->hasErrors(), sprintf(
                'Expected to find %s, but found %s',
                $this->expectedErrors($toUnicodeStatus),
                $this->containedErrors($result->getErrors())
            ));
        }
    }

    /**
     * @dataProvider getData
     */
    public function testToAsciiNonTransitional(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus
    ): void {
        [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ] = $this->translate($source, $toUnicode, $toUnicodeStatus, $toAsciiN, $toAsciiNStatus, $toAsciiT, $toAsciiTStatus);
        $result = Idna::toAscii($source, self::DEFAULT_OPTIONS);

        if ($toAsciiNStatus === []) {
            self::assertSame($toAsciiN, $result->getDomain());
            self::assertFalse($result->hasErrors(), sprintf(
                'Expected no errors, but found %s.',
                $this->containedErrors($result->getErrors())
            ));
        } else {
            self::assertTrue($result->hasErrors(), sprintf(
                'Expected %s, but found no errors.',
                $this->expectedErrors($toAsciiTStatus)
            ));
        }
    }

    /**
     * @dataProvider getData
     */
    public function testToAsciiTransitional(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus
    ): void {
        [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ] = $this->translate($source, $toUnicode, $toUnicodeStatus, $toAsciiN, $toAsciiNStatus, $toAsciiT, $toAsciiTStatus);
        $options = self::DEFAULT_OPTIONS;
        $options['Transitional_Processing'] = true;
        $result = Idna::toAscii($source, $options);

        // There is currently a bug in the test data, where it is expected that the following 2
        // source strings result in an empty string. However, due to the way the test files are setup
        // it currently isn't possible to represent an empty string as an expected value. So, we
        // skip these 2 problem tests. I have notified the Unicode Consortium about this and they
        // have passed the information along to the spec editors.
        if ($source === "\u{200C}" || $source === "\u{200D}") {
            $toAsciiT = '';
        }

        if ($toAsciiTStatus === []) {
            self::assertSame($toAsciiT, $result->getDomain());
            self::assertFalse($result->hasErrors(), sprintf(
                'Expected no errors, but found %s.',
                $this->containedErrors($result->getErrors())
            ));
        } else {
            self::assertTrue($result->hasErrors(), sprintf(
                'Expected %s, but found no errors.',
                $this->expectedErrors($toAsciiTStatus)
            ));
        }
    }

    public function assertErrors(array $expectedErrors, int $errors): void
    {
        foreach ($expectedErrors as $errorCode) {
            if (is_array($errorCode)) {
                self::assertTrue($this->matchErrors($errorCode, $errors));

                continue;
            }

            self::assertTrue(
                ($errors & $errorCode) !== 0,
                sprintf(
                    'Expected %s (%d), but only found %s.',
                    self::$errorMap[$errorCode],
                    $errorCode,
                    $this->containedErrors($errors)
                )
            );
        }
    }

    public function expectedErrors(array $expectedErrors): string
    {
        $message = '';

        foreach ($expectedErrors as $i => $errorCode) {
            if (is_array($errorCode)) {
                $message .= '(';

                foreach ($errorCode as $j => $error) {
                    if ($j > 0) {
                        $message .= ' OR ';
                    }

                    $message .= sprintf('%s (%d)', self::$errorMap[$error], $error);
                }

                $message .= ')';
            } else {
                if ($i > 0) {
                    $message .= ' | ';
                }

                $message .= sprintf('%s (%d)', self::$errorMap[$errorCode], $errorCode);
            }
        }

        return $message;
    }

    public function containedErrors(int $errors): string
    {
        if ($errors === 0) {
            return 'no errors';
        }

        $out = '';
        $count = 0;

        foreach (self::$errorMap as $code => $name) {
            if (($errors & $code) !== 0) {
                if ($count++ > 0) {
                    $out .= ' | ';
                }

                $out .= sprintf('%s (%d)', $name, $code);
            }
        }

        return $out;
    }

    public function matchErrors(array $potentialErrors, int $errors): bool
    {
        foreach ($potentialErrors as $error) {
            if (($errors & $error) !== 0) {
                return true;
            }
        }

        return false;
    }
}
