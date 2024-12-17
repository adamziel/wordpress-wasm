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

namespace Webuni\FrontMatter\Twig;

use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Processor\ProcessorInterface;
use Webuni\FrontMatter\Processor\YamlProcessor;

/**
 * @see https://plugins.craftcms.com/front-matter
 */
final class TwigCommentFrontMatter
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
        // prevent any instantiation
    }

    public static function create(ProcessorInterface $processor = null): FrontMatter
    {
        return new FrontMatter($processor ?? new YamlProcessor(), '{#---', '---#}');
    }
}
