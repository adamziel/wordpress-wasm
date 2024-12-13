<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use Rowbot\URL\String\Exception\RegexException;
use Stringable;

use function explode;
use function intval;
use function mb_strlen;
use function mb_substr;
use function preg_last_error_msg;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strspn;

use const PHP_INT_MAX;

abstract class AbstractUSVString implements USVStringInterface
{
    /**
     * @var string
     */
    protected $string;

    public function __construct(string $string = '')
    {
        $this->string = $string;
    }

    public function append(string $string): USVStringInterface
    {
        $copy = clone $this;
        $copy->string .= $string;

        return $copy;
    }

    public function endsWith(string $string): bool
    {
        return substr_compare($this->string, $string, -strlen($string)) === 0;
    }

    public function getIterator(): StringIteratorInterface
    {
        return new Utf8StringIterator($this->string);
    }

    public function isEmpty(): bool
    {
        return $this->string === '';
    }

    public function length(): int
    {
        return mb_strlen($this->string, 'utf-8');
    }

    public function matches(string $pattern, ?array &$matches = null, int $flags = 0, int $offset = 0): bool
    {
        $result = preg_match($pattern, $this->string, $matches, $flags, $offset);

        if ($result === false) {
            throw new RegexException(sprintf('preg_match encountered an error with message "%s".', preg_last_error_msg()));
        }

        return $result === 1;
    }

    public function replaceRegex(
        string $pattern,
        string $replacement,
        int $limit = -1,
        int &$count = 0
    ): USVStringInterface {
        $result = preg_replace($pattern, $replacement, $this->string, $limit, $count);

        if ($result === null) {
            throw new RegexException(sprintf('preg_replace encountered an error with message "%s".', preg_last_error_msg()));
        }

        $copy = clone $this;
        $copy->string = $result;

        return $copy;
    }

    public function split(string $delimiter, ?int $limit = null): StringListInterface
    {
        if ($delimiter === '') {
            return new StringList();
        }

        /** @var non-empty-list<string> $list */
        $list = explode($delimiter, $this->string, $limit ?? PHP_INT_MAX);
        $temp = [];

        foreach ($list as $string) {
            $copy = clone $this;
            $copy->string = $string;
            $temp[] = $copy;
        }

        return new StringList($temp);
    }

    public function startsWith(string $string): bool
    {
        return strncmp($this->string, $string, strlen($string)) === 0;
    }

    public function startsWithTwoAsciiHexDigits(): bool
    {
        if (!isset($this->string[1])) {
            return false;
        }

        return strspn($this->string, CodePoint::HEX_DIGIT_MASK, 0, 2) === 2;
    }

    /**
     * @see https://url.spec.whatwg.org/#start-with-a-windows-drive-letter
     */
    public function startsWithWindowsDriveLetter(): bool
    {
        return preg_match('/^[A-Za-z][:|](?:$|[\/\\\?#])/u', $this->string) === 1;
    }

    public function substr(int $start, ?int $length = null): USVStringInterface
    {
        $copy = clone $this;
        $copy->string = mb_substr($this->string, $start, $length, 'utf-8');

        return $copy;
    }

    public function toInt(int $base = 10): int
    {
        return intval($this->string, $base);
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
