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

use Yosymfony\Toml\Toml;

final class TomlProcessor implements ProcessorInterface
{
    public function parse(string $string): array
    {
        return (array) Toml::parse($string);
    }

    public function dump(array $data): string
    {
        throw new \BadMethodCallException('Dump for Toml is not implemented.');
    }
}
