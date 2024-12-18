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

interface ProcessorInterface
{
    /**
     * Parses front matter string into a data.
     *
     * @param string $string The string
     *
     * @return array
     */
    public function parse(string $string): array;

    /**
     * Dumps a data to a string.
     *
     * @param array $data The data
     *
     * @return string
     */
    public function dump(array $data): string;
}
