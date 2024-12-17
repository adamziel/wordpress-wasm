<?php

/*
 * This is part of the webuni/front-matter package.
 *
 * (c) Martin Hasoň <martin.hason@gmail.com>
 * (c) Webuni s.r.o. <info@webuni.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webuni\FrontMatter\Processor;

abstract class ProcessorDecorator implements ProcessorInterface
{
    private ProcessorInterface $wrapped;

    public function __construct(ProcessorInterface $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function parse(string $string): array
    {
        return $this->wrapped->parse($string);
    }

    public function dump(array $data): string
    {
        return $this->wrapped->dump($data);
    }
}
