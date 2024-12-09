<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use PHPUnit\Framework\TestCase;
use Rowbot\URL\Component\QueryList;

class QueryListTest extends TestCase
{
    public function testSetDoesNothingWhenNoMatchingNameExists(): void
    {
        $input = [
            ['name' => 'a', 'value' => 'b'],
            ['name' => 'a', 'value' => 'c'],
        ];
        $list = new QueryList($input);
        $list->set('Foo', 'Bar');
        self::assertSame($input, $list->getIterator()->getArrayCopy());
    }
}
