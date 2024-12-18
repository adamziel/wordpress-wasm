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

final class JsonWithoutBracesProcessor implements ProcessorInterface
{
    public function parse(string $string): array
    {
        if (false !== strpos($string, '":')) {
            $string = '{'.$string.'}';
        }

        return (array) json_decode($string, true);
    }

    public function dump(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $result = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ('{' === substr($result, 0, 1) && '}' === substr($result, -1)) {
            $result = substr($result, 1, -1);
        }

        return $result;
    }
}
