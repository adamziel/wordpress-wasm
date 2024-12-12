<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Rowbot\URL\Component\Scheme;

class SchemeTest extends TestCase
{
    public static function specialSchemeNonNullDefaultPortProvider(): iterable
    {
        $reflection = new ReflectionClass(Scheme::class);
        $schemes = $reflection->getConstant('SPECIAL_SCHEMES');

        foreach ($schemes as $scheme => $port) {
            if ($port === null) {
                continue;
            }

            yield [$scheme, $port];
        }
    }

    public function testIsDefaultPortReturnsTrueForNonNullPortSpecialSchemes(string $scheme, int $port): void
    {
        $scheme = new Scheme($scheme);
        self::assertTrue($scheme->isDefaultPort($port));
    }

    public function testIsDefaultPortReturnsFalseForNonSpecialSchemesAndNullPorts(string $scheme, ?int $port): void
    {
        $scheme = new Scheme($scheme);
        self::assertFalse($scheme->isDefaultPort($port));
    }
}
