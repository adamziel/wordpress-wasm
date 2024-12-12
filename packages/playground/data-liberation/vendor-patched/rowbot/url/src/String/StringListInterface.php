<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use Countable;
use IteratorAggregate;

/**
 * @extends \IteratorAggregate<int, \Rowbot\URL\String\USVStringInterface>
 */
interface StringListInterface extends Countable, IteratorAggregate
{
    /**
     * @return \Rowbot\URL\String\USVStringInterface
     */
    public function first();

    public function isEmpty(): bool;

    /**
     * @return \Rowbot\URL\String\USVStringInterface
     */
    public function last();

    /**
     * @return \Rowbot\URL\String\USVStringInterface|null
     */
    public function pop();

    /**
     * @param \Rowbot\URL\String\USVStringInterface $item
     */
    public function push($item): void;
}
