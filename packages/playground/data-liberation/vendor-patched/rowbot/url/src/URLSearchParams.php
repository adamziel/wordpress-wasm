<?php

declare(strict_types=1);

namespace Rowbot\URL;

use Countable;
use Iterator;
use ReflectionObject;
use ReflectionProperty;
use Rowbot\URL\Component\QueryList;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\String\Utf8String;
use Stringable;

use function array_column;
use function count;
use function func_num_args;
use function get_debug_type;
use function is_countable;
use function is_iterable;
use function is_object;
use function is_scalar;
use function sprintf;
use function substr;

/**
 * An object containing a list of all URL query parameters. This allows you to manipulate a URL's
 * query string in a granular manner.
 *
 * @see https://url.spec.whatwg.org/#urlsearchparams
 * @see https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams
 *
 * @implements \Iterator<int, array{0: string, 1: string}|null>
 *
 * @property-read int<0, max> $size
 */
class URLSearchParams implements Countable, Iterator
{
    /**
     * @var 0|positive-int
     */
    private $cursor;

    /**
     * @var \Rowbot\URL\Component\QueryList
     */
    private $list;

    /**
     * @var \Rowbot\URL\URLRecord|null
     */
    private $url;

    /**
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-urlsearchparams
     *
     * @param iterable<int|string, array<int|string, scalar|\Stringable>|(\Traversable<int|string, scalar|\Stringable>&\Countable)>|object|string|\Stringable $init
     */
    public function __construct($init = '')
    {
        $this->list = new QueryList();
        $this->url = null;
        $this->cursor = 0;

        if (func_num_args() < 1) {
            return;
        }

        if ($this->isStringable($init)) {
            $init = Utf8String::scrub((string) $init);

            if ($init !== '' && $init[0] === '?') {
                $init = substr($init, 1);
            }

            $this->list = QueryList::fromString($init);

            return;
        }

        if (is_iterable($init)) {
            $this->initIterator($init);

            return;
        }

        if (is_object($init)) {
            $this->initObject($init);

            return;
        }
    }

    /**
     * Appends a new name-value pair to the end of the query string.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-append
     *
     * @param string $name  The name of the key in the pair.
     * @param string $value The value assigned to the key.
     */
    public function append(string $name, string $value): void
    {
        $this->list->append(Utf8String::scrub($name), Utf8String::scrub($value));
        $this->update();
    }

    public function count(): int
    {
        return $this->list->count();
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function current(): ?array
    {
        $tuple = $this->list->getTupleAt($this->cursor);

        if ($tuple === null) {
            return null;
        }

        return [$tuple['name'], $tuple['value']];
    }

    /**
     * Deletes all occurances of pairs with the specified key name.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-delete
     *
     * @param string $name The name of the key to delete.
     */
    public function delete(string $name, ?string $value = null): void
    {
        if ($value !== null) {
            $value = Utf8String::scrub($value);
        }

        $this->list->remove(Utf8String::scrub($name), $value);
        $this->update();
    }

    /**
     * Get the value of the first name-value pair with the specified key name.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-get
     *
     * @param string $name The name of the key whose value you want to retrive.
     *
     * @return string|null The value of the specified key.
     */
    public function get(string $name): ?string
    {
        return $this->list->first(Utf8String::scrub($name));
    }

    /**
     * Gets all name-value pairs that has the specified key name.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-getall
     *
     * @param string $name The name of the key whose values you want to retrieve.
     *
     * @return array<int, string> An array containing all the values of the specified key.
     */
    public function getAll(string $name): array
    {
        $name = Utf8String::scrub($name);

        return array_column($this->list->filter(static function (array $pair) use ($name) : bool {
            return $pair['name'] === $name;
        }), 'value');
    }

    /**
     * Indicates whether or not a query string contains any keys with the specified key name.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-has
     *
     * @param string $name The key name you want to test if it exists.
     *
     * @return bool Returns true if the key exits, otherwise false.
     */
    public function has(string $name, ?string $value = null): bool
    {
        if ($value !== null) {
            $value = Utf8String::scrub($value);
        }

        return $this->list->contains(Utf8String::scrub($name), $value);
    }

    public function key(): int
    {
        return $this->cursor;
    }

    public function next(): void
    {
        ++$this->cursor;
    }

    public function rewind(): void
    {
        $this->cursor = 0;
    }

    /**
     * Sets the value of the specified key name. If multiple pairs exist with the same key name it
     * will set the value for the first occurance of the key in the query string and all other
     * occurances will be removed from the query string.  If the key does not already exist in the
     * query string, it will be added to the end of the query string.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-set
     *
     * @param string $name  The name of the key you want to modify the value of.
     * @param string $value The value you want to associate with the key name.
     */
    public function set(string $name, string $value): void
    {
        $name = Utf8String::scrub($name);
        $value = Utf8String::scrub($value);

        if ($this->list->contains($name)) {
            $this->list->set($name, $value);
        } else {
            $this->list->append($name, $value);
        }

        $this->update();
    }

    /**
     * @internal
     */
    public function setList(QueryList $list): void
    {
        $this->list = $list;
    }

    /**
     * Sets the associated url record.
     *
     * @internal
     */
    public function setUrl(URLRecord $url): void
    {
        $this->url = $url;
    }

    /**
     * Sorts the list of search params by their names by comparing their code unit values,
     * preserving the relative order between pairs with the same name.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-sort
     */
    public function sort(): void
    {
        $this->list->sort();
        $this->update();
    }

    public function toString(): string
    {
        return $this->list->toUrlencodedString();
    }

    /**
     * @phpstan-assert-if-true array{0: string, 1: string} $this->current()
     */
    public function valid(): bool
    {
        return $this->list->getTupleAt($this->cursor) !== null;
    }

    /**
     * Set's the associated URL object's query to the serialization of URLSearchParams.
     *
     * @see https://url.spec.whatwg.org/#concept-urlsearchparams-update
     *
     * @internal
     */
    protected function update(): void
    {
        if ($this->url === null) {
            return;
        }

        $query = $this->list->toUrlencodedString();

        if ($query === '') {
            $query = null;
        }

        $this->url->query = $query;

        if ($query === null) {
            $this->url->path->potentiallyStripTrailingSpaces($this->url);
        }
    }

    /**
     * @param iterable<int|string, iterable<int|string, scalar|\Stringable>&\Countable> $input
     *
     * @throws \Rowbot\URL\Exception\TypeError
     */
    private function initIterator(iterable $input): void
    {
        foreach ($input as $key => $pair) {
            // Try to catch cases where $pair isn't countable or $pair is
            // countable, but isn't a valid sequence, such as:
            //
            // class CountableClass implements \Countable
            // {
            //     public function count()
            //     {
            //         return 2;
            //     }
            // }
            //
            // $s = new \Rowbot\URL\URLSearchParams([new CountableClass()]);
            //
            // or:
            //
            // $a = new \ArrayObject(['x', 'y']);
            // $s = new \Rowbot\URL\URLSearchParams($a);
            //
            // while still allowing things like:
            //
            // $a = new \ArrayObject(new \ArrayObject(['x', 'y']));
            // $s = new \Rowbot\URL\URLSearchParams($a);'
            if (!(is_array($pair) || $pair instanceof \Countable) || !is_iterable($pair)) {
                throw new TypeError(sprintf(
                    'Expected a valid sequence such as an Array or iterable Object that implements '
                    . 'the \\Countable interface. %s found instead.',
                    get_debug_type($pair)
                ));
            }

            if (count($pair) !== 2) {
                throw new TypeError(sprintf(
                    'Expected sequence with exactly 2 items. Sequence contained %d items.',
                    count($pair)
                ));
            }

            [$name, $value] = $pair;

            if (!$this->isStringable($name)) {
                throw new TypeError(sprintf(
                    'The name of the name-value pair at index "%s" must be a scalar value or stringable.',
                    $key
                ));
            }

            if (!$this->isStringable($value)) {
                throw new TypeError(sprintf(
                    'The value of the name-value pair at index "%s" must be a scalar value or stringable.',
                    $key
                ));
            }

            $this->list->append(Utf8String::scrub((string) $name), Utf8String::scrub((string) $value));
        }
    }

    private function initObject(object $input): void
    {
        $reflection = new ReflectionObject($input);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($input);

            if (!$this->isStringable($value)) {
                throw new TypeError(sprintf(
                    'The value of property "%s" must be a scalar value or \\Stringable.',
                    $reflection->getName()
                ));
            }

            $this->list->append(Utf8String::scrub($property->getName()), Utf8String::scrub((string) $value));
        }
    }

    /**
     * @phpstan-assert-if-true scalar|\Stringable $value
     * @param mixed $value
     */
    private function isStringable($value): bool
    {
        return $value instanceof Stringable || is_scalar($value);
    }

    public function __clone()
    {
        $this->list = clone $this->list;

        // Null out the url in-case someone tries cloning the object returned by
        // the URL::searchParams attribute.
        $this->url = null;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'size':
                return $this->list->count();
            default:
                return null;
        }
    }

    /**
     * Returns all name-value pairs stringified in the correct order.
     */
    public function __toString(): string
    {
        return $this->list->toUrlencodedString();
    }
}
