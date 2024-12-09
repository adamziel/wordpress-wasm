<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\Component\Host\HostInterface;
use Rowbot\URL\Component\Host\HostParser;
use Rowbot\URL\ParserContext;
use Rowbot\URL\String\StringBuffer;
use Rowbot\URL\String\Utf8String;
use Rowbot\URL\URLRecord;

class HostParserTest extends TestCase
{
    /**
     * @see https://url.spec.whatwg.org/#example-host-parsing
     */
    public function testHostParser(string $input, array $output): void
    {
        $parser = new HostParser();
        $in = new Utf8String($input);
        $context = new ParserContext($in, $in->getIterator(), new StringBuffer(), new URLRecord(), null, null, null, null);
        foreach ($output as $i => $expected) {
            $isOpaque = $i % 2 === 1;
            $host = $parser->parse($context, $in, $isOpaque);

            if ($expected === false) {
                self::assertFalse($host);
            } else {
                self::assertInstanceOf(HostInterface::class, $host);
                self::assertSame($expected, $host->getSerializer()->toFormattedString());
            }
        }
    }

    public static function exampleDataProvider(): array
    {
        return [
            ['input' => 'EXAMPLE.COM', 'output' => ['example.com', 'EXAMPLE.COM']],
            ['input' => 'example%2Ecom', 'output' => ['example.com', 'example%2Ecom']],
            ['input' => 'faß.example', 'output' => ['xn--fa-hia.example', 'fa%C3%9F.example']],
            ['input' => '0', 'output' => ['0.0.0.0', '0']],
            ['input' => '%30', 'output' => ['0.0.0.0', '%30']],
            ['input' => '0x', 'output' => ['0.0.0.0', '0x']],
            ['input' => '0xffffffff', 'output' => ['255.255.255.255', '0xffffffff']],
            ['input' => '[0:0::1]', 'output' => ['[::1]', '[::1]']],
            ['input' => '[0:0::1%5D', 'output' => [false, false]],
            ['input' => '[0:0::%31]', 'output' => [false, false]],
            ['input' => '09', 'output' => [false, '09']],
            ['input' => 'example.255', 'output' => [false, 'example.255']],
            ['input' => 'example^example', 'output' => [false, false]],
        ];
    }
}
