<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\Component\Host\NullHost;
use Rowbot\URL\Component\Host\StringHost;

class NullHostTest extends TestCase
{
    public function testNullHostSerializesToEmptyString(): void
    {
        $host = new NullHost();
        $serializer = $host->getSerializer();
        self::assertEmpty($serializer->toFormattedString());
        self::assertEmpty($serializer->toString());
    }

    public function testNullHostIsEqualOnlyToItself(): void
    {
        $host = new NullHost();
        self::assertTrue($host->equals($host));
        self::assertTrue($host->equals(new NullHost()));
        self::assertFalse($host->equals(new StringHost()));
    }
}
