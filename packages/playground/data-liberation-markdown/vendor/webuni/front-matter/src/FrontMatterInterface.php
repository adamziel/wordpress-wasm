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

namespace Webuni\FrontMatter;

/**
 * Interface FrontMatterInterface.
 */
interface FrontMatterInterface
{
    /**
     * Check if a source has a front matter.
     *
     * @param string $source The source
     *
     * @return bool If the source has a front matter
     */
    public function exists(string $source): bool;

    /**
     * Parse source.
     *
     * @param string $source The source
     *
     * @return Document
     */
    public function parse(string $source): Document;

    /**
     * Dump document.
     *
     * @param Document $document The document
     *
     * @return string
     */
    public function dump(Document $document): string;
}
