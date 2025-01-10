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

class Document
{
    /** @var string */
    private $content;

    /** @var array<string, mixed> */
    private $data;

    /**
     * @param string $content
     * @param array<string, mixed> $data
     */
    public function __construct(string $content = '', array $data = [])
    {
        $this->content = $content;
        $this->data = $data;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getDataWithContent(string $key = '__content'): array
    {
        return array_merge($this->data, [$key => $this->content]);
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     * @return $this
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
