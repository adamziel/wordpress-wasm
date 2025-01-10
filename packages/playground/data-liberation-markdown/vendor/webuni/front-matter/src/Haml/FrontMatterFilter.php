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

namespace Webuni\FrontMatter\Haml;

use MtHaml\Filter\FilterInterface;
use MtHaml\Node\Filter;
use MtHaml\NodeVisitor\RendererAbstract;
use Webuni\FrontMatter\Document;
use Webuni\FrontMatter\FrontMatterInterface;

class FrontMatterFilter implements FilterInterface
{
    /** @var FrontMatterInterface */
    private $parser;

    /** @var FilterInterface */
    private $filter;

    public function __construct(FrontMatterInterface $parser, FilterInterface $filter)
    {
        $this->parser = $parser;
        $this->filter = $filter;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function isOptimizable(RendererAbstract $renderer, Filter $node, $options): bool
    {
        return $this->filter->isOptimizable($renderer, $node, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function optimize(RendererAbstract $renderer, Filter $node, $options): string
    {
        return $this->filter->optimize($renderer, $node, $options);
    }

    /**
     * @param string $content
     * @param array<mixed, mixed> $context
     * @param array<string, mixed> $options
     */
    public function filter($content, array $context, $options): Document
    {
        $document = $this->parser->parse($content);
        $document->setContent($this->filter->filter($document, $context, $options));

        return $document;
    }
}
