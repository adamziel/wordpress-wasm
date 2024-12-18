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

use Nette\Neon\Neon;

final class NeonProcessor implements ProcessorInterface
{
    public function parse(string $string): array
    {
        return (array) Neon::decode($string);
    }

    public function dump(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        return Neon::encode($data, Neon::BLOCK);
    }
}
