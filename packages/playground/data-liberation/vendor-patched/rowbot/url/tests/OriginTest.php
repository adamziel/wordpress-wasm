<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rowbot\URL\BasicURLParser;
use Rowbot\URL\Component\Host\HostParser;
use Rowbot\URL\Component\OpaqueOrigin;
use Rowbot\URL\Component\TupleOrigin;
use Rowbot\URL\Origin;
use Rowbot\URL\ParserContext;
use Rowbot\URL\String\StringBuffer;
use Rowbot\URL\String\Utf8String;
use Rowbot\URL\String\Utf8StringIterator;
use Rowbot\URL\URLRecord;

class OriginTest extends TestCase
{
    public static function originProvider(): array
    {
        $hostParser = new HostParser();
        $context = new ParserContext(
            new Utf8String(''),
            new Utf8StringIterator(''),
            new StringBuffer(''),
            new URLRecord(),
            null,
            null,
            null,
            null
        );
        $tuple = new TupleOrigin(
            'https',
            $hostParser->parse($context, new Utf8String('example.org'), false),
            null,
            null
        );
        $tupleDomain = new TupleOrigin(
            'https',
            $hostParser->parse($context, new Utf8String('example.org'), false),
            null,
            'example.org'
        );
        $opaque = new OpaqueOrigin();
        $urlParser = new BasicURLParser();

        return [
            [
                $tuple,
                new TupleOrigin(
                    'https',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    null,
                    null
                ),
                'isSameOrigin' => true,
                'isSameOriginDomain' => true,
            ],
            [
                new TupleOrigin('https', $hostParser->parse($context, new Utf8String('example.org'), false), 314, null),
                new TupleOrigin('https', $hostParser->parse($context, new Utf8String('example.org'), false), 420, null),
                'isSameOrigin' => false,
                'isSameOriginDomain' => false,
            ],
            [
                new TupleOrigin(
                    'https',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    314,
                    'example.org'
                ),
                new TupleOrigin(
                    'https',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    420,
                    'example.org'
                ),
                'isSameOrigin' => false,
                'isSameOriginDomain' => true,
            ],
            [
                new TupleOrigin(
                    'https',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    null,
                    null
                ),
                new TupleOrigin(
                    'https',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    null,
                    'example.org'
                ),
                'isSameOrigin' => true,
                'isSameOriginDomain' => false,
            ],
            [
                new TupleOrigin(
                    'https',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    null,
                    'example.org'
                ),
                new TupleOrigin(
                    'http',
                    $hostParser->parse($context, new Utf8String('example.org'), false),
                    null,
                    'example.org'
                ),
                'isSameOrigin' => false,
                'isSameOriginDomain' => false,
            ],
            [
                new TupleOrigin('https', $hostParser->parse($context, new Utf8String('127.0.0.1'), false), null, null),
                new TupleOrigin('https', $hostParser->parse($context, new Utf8String('1.1.1.1'), false), null, null),
                'isSameOrigin' => false,
                'isSameOriginDomain' => false,
            ],
            [
                new TupleOrigin('https', $hostParser->parse($context, new Utf8String('[::1]'), false), null, null),
                new TupleOrigin('https', $hostParser->parse($context, new Utf8String('[1::1]'), false), null, null),
                'isSameOrigin' => false,
                'isSameOriginDomain' => false,
            ],
            [
                $urlParser->parse(new Utf8String('blob:https://foo.com'))->getOrigin(),
                $urlParser->parse(new Utf8String('https://foo.com'))->getOrigin(),
                'isSameOrigin' => true,
                'isSameOriginDomain' => true,
            ],
            [
                $tuple,
                $tuple,
                'isSameOrigin' => true,
                'isSameOriginDomain' => true,
            ],
            [
                $tuple,
                $tupleDomain,
                'isSameOrigin' => true,
                'isSameOriginDomain' => false,
            ],
            [
                $opaque,
                new OpaqueOrigin(),
                'isSameOrigin' => false,
                'isSameOriginDomain' => false,
            ],
            [
                $opaque,
                $opaque,
                'isSameOrigin' => true,
                'isSameOriginDomain' => true,
            ],
            [
                $tuple,
                $opaque,
                'isSameOrigin' => false,
                'isSameOriginDomain' => false,
            ],
        ];
    }

    public function testSameOriginConcept(Origin $originA, Origin $originB, bool $isSameOrigin, bool $isSameOriginDomain): void
    {
        self::assertSame($isSameOrigin, $originA->isSameOrigin($originB));
        self::assertSame($isSameOriginDomain, $originA->isSameOriginDomain($originB));
    }

    public function testEffectiveDomainConcept(): void
    {
        $origin = new OpaqueOrigin();
        self::assertTrue($origin->isOpaque());
        self::assertNull($origin->getEffectiveDomain());

        $parser = new BasicURLParser();
        $record = $parser->parse(new Utf8String('blob:https://foo.com'));
        $origin = $record->getOrigin();
        self::assertFalse($origin->isOpaque());
        self::assertNotNull($origin->getEffectiveDomain());
        self::assertSame('foo.com', $origin->getEffectiveDomain());

        $hostParser = new HostParser();
        $context = new ParserContext(
            new Utf8String(''),
            new Utf8StringIterator(''),
            new StringBuffer(''),
            new URLRecord(),
            null,
            null,
            null,
            null
        );
        $origin = new TupleOrigin(
            'https',
            $hostParser->parse($context, new Utf8String('example.org'), false),
            314,
            'example.org'
        );
        self::assertFalse($origin->isOpaque());
        self::assertNotNull($origin->getEffectiveDomain());
        self::assertSame('example.org', $origin->getEffectiveDomain());
    }
}
