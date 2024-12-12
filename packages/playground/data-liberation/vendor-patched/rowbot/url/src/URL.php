<?php

declare(strict_types=1);

namespace Rowbot\URL;

use InvalidArgumentException;
use JsonSerializable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Rowbot\URL\Component\PathList;
use Rowbot\URL\Component\QueryList;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\String\Utf8String;
use Throwable;

use function assert;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Represents a URL that can be manipulated.
 *
 * @see https://url.spec.whatwg.org/#api
 * @see https://developer.mozilla.org/en-US/docs/Web/API/URL
 *
 * @property string                      $href
 * @property string                      $origin
 * @property string                      $protocol
 * @property string                      $username
 * @property string                      $password
 * @property string                      $host
 * @property string                      $hostname
 * @property string                      $port
 * @property string                      $pathname
 * @property string                      $search
 * @property \Rowbot\URL\URLSearchParams $searchParams
 * @property string                      $hash
 */
class URL implements JsonSerializable, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var \Rowbot\URL\URLSearchParams
     */
    private $queryObject;

    /**
     * @var \Rowbot\URL\URLRecord
     */
    private $url;

    /**
     * @param array{logger?: \Psr\Log\LoggerInterface|null} $options
     *
     * @throws \Rowbot\URL\Exception\TypeError
     * @param string|\Stringable $url
     * @param string|\Stringable|null $base
     */
    public function __construct($url, $base = null, array $options = [])
    {
        $this->logger = null;

        if (isset($options['logger'])) {
            if (!$options['logger'] instanceof LoggerInterface) {
                throw new TypeError(sprintf(
                    'The passed logger must be null or an instance of %s',
                    LoggerInterface::class
                ));
            }

            $this->logger = $options['logger'];
        }

        // 1. Let parsedURL be the result of running the API URL parser on url with base, if given.
        $parsedURL = self::parseURL($url, $base, $this->logger);

        if ($parsedURL->error === APIParserErrorType::NONE) {
            // 3. Initialize this with parsedURL.
            assert($parsedURL->url !== null);
            self::initializeURL($this, $parsedURL->url);

            return;
        }

        switch ($parsedURL->error) {
            case APIParserErrorType::BASE:
                $message = 'Invalid base URL';
                break;
            case APIParserErrorType::URL:
                $message = 'Invalid URL';
                break;
        }

        throw new TypeError($message);
    }

    /**
     * @see https://url.spec.whatwg.org/#dom-url-parse
     * @param string|\Stringable $url
     * @param string|\Stringable|null $base
     */
    public static function parse($url, $base = null): ?self
    {
        try {
            return new self($url, $base);
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * @see https://url.spec.whatwg.org/#dom-url-canparse
     * @param string|\Stringable $url
     * @param string|\Stringable|null $base
     */
    public static function canParse($url, $base = null): bool
    {
        $parsedURL = self::parseURL($url, $base);

        return $parsedURL->error === APIParserErrorType::NONE;
    }

    public function toString(): string
    {
        return $this->url->serializeURL();
    }

    /**
     * Returns a JSON encoded string without escaping forward slashes. If you
     * need forward slashes to be escaped, pass the URL object to json_encode()
     * instead of calling this method.
     *
     * @see https://url.spec.whatwg.org/#dom-url-tojson
     */
    public function toJSON(): string
    {
        // Use JSON_UNESCAPED_SLASHES here since JavaScript's JSON.stringify()
        // method does not escape forward slashes by default.
        return json_encode($this->url->serializeURL(), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns the serialized URL for consumption by json_encode(). To match
     * JavaScript's behavior, you should pass the JSON_UNESCAPED_SLASHES option
     * to json_encode().
     */
    public function jsonSerialize(): string
    {
        return $this->url->serializeURL();
    }

    /**
     * @see https://url.spec.whatwg.org/#api-url-parser
     * @param string|\Stringable $url
     * @param string|\Stringable|null $base
     */
    private static function parseURL(
        $url,
        $base = null,
        ?LoggerInterface $logger = null
    ): APIParserResult {
        // 1. Let parsedBase be null.
        $parsedBase = null;
        $parser = new BasicURLParser($logger);

        // 2. If base is non-null:
        if ($base !== null) {
            // 2.1. Set parsedBase to the result of running the basic URL parser on base.
            $stringBase = (string) $base;
            $parsedBase = $parser->parse(Utf8String::fromUnsafe($stringBase));

            // 2.2. If parsedBase is failure, then return failure.
            if ($parsedBase === false) {
                return new APIParserResult(null, APIParserErrorType::BASE);
            }
        }

        // 3. Return the result of running the basic URL parser on url with parsedBase.
        $stringURL = (string) $url;
        $parsedURL = $parser->parse(Utf8String::fromUnsafe($stringURL), $parsedBase);

        if ($parsedURL === false) {
            return new APIParserResult(null, APIParserErrorType::URL);
        }

        return new APIParserResult($parsedURL, APIParserErrorType::NONE);
    }

    /**
     * @see https://url.spec.whatwg.org/#url-initialize
     */
    private static function initializeURL(self $url, URLRecord $urlRecord): void
    {
        // 1. Let query be urlRecord’s query, if that is non-null; otherwise the empty string.
        $query = $urlRecord->query ?? '';

        // 2. Set url’s URL to urlRecord.
        $url->url = $urlRecord;

        // 3. Set url’s query object to a new URLSearchParams object.
        $url->queryObject = new URLSearchParams();

        // 4. Initialize url’s query object with query.
        $url->queryObject->setList(QueryList::fromString($query));

        // 5. Set url’s query object’s URL object to url.
        $url->queryObject->setUrl($urlRecord);
    }

    public function __clone()
    {
        $this->url = clone $this->url;
        $this->queryObject = clone $this->queryObject;
        $this->queryObject->setUrl($this->url);
    }

    /**
     * @throws \InvalidArgumentException When an invalid $name value is passed.
     * @return string|\Rowbot\URL\URLSearchParams
     */
    public function __get(string $name)
    {
        if ($name === 'hash') {
            if ($this->url->fragment === null || $this->url->fragment === '') {
                return '';
            }

            return '#' . $this->url->fragment;
        }

        if ($name === 'host') {
            if ($this->url->host->isNull()) {
                return '';
            }

            $serializer = $this->url->host->getSerializer();

            if ($this->url->port === null) {
                return $serializer->toFormattedString();
            }

            return $serializer->toFormattedString() . ':' . $this->url->port;
        }

        if ($name === 'hostname') {
            if ($this->url->host->isNull()) {
                return '';
            }

            return $this->url->host->getSerializer()->toFormattedString();
        }

        if ($name === 'href') {
            return $this->url->serializeURL();
        }

        if ($name === 'origin') {
            return (string) $this->url->getOrigin();
        }

        if ($name === 'password') {
            return $this->url->password;
        }

        if ($name === 'pathname') {
            return (string) $this->url->path;
        }

        if ($name === 'port') {
            if ($this->url->port === null) {
                return '';
            }

            return (string) $this->url->port;
        }

        if ($name === 'protocol') {
            return $this->url->scheme . ':';
        }

        if ($name === 'search') {
            if ($this->url->query === null || $this->url->query === '') {
                return '';
            }

            return '?' . $this->url->query;
        }

        if ($name === 'searchParams') {
            return $this->queryObject;
        }

        if ($name === 'username') {
            return $this->url->username;
        }

        throw new InvalidArgumentException(sprintf('"%s" is not a valid property.', $name));
    }

    /**
     * @throws \InvalidArgumentException       When an invalid $name or $value value is passed.
     * @throws \Rowbot\URL\Exception\TypeError Only when trying to set URL::$searchParams
     */
    public function __set(string $name, string $value): void
    {
        if ($name === 'searchParams') {
            throw new TypeError('Cannot redefine the searchParams property.');
        }

        $input = Utf8String::fromUnsafe($value);
        $parser = new BasicURLParser($this->logger);

        if ($name === 'hash') {
            if ($input->isEmpty()) {
                $this->url->fragment = null;
                $this->url->path->potentiallyStripTrailingSpaces($this->url);

                // Terminate these steps
                return;
            }

            if ($input->startsWith('#')) {
                $input = $input->substr(1);
            }

            $this->url->fragment = '';
            $parser->parse($input, null, null, $this->url, ParserState::FRAGMENT);
        } elseif ($name === 'host') {
            if ($this->url->path->isOpaque()) {
                // Terminate these steps
                return;
            }

            $parser->parse($input, null, null, $this->url, ParserState::HOST);
        } elseif ($name === 'hostname') {
            if ($this->url->path->isOpaque()) {
                // Terminate these steps
                return;
            }

            $parser->parse($input, null, null, $this->url, ParserState::HOSTNAME);
        } elseif ($name === 'href') {
            $parsedURL = $parser->parse($input);

            if ($parsedURL === false) {
                throw new TypeError(sprintf('"%s" is not a valid URL.', $value));
            }

            $this->url = $parsedURL;
            $this->queryObject->setUrl($this->url);

            if ($this->url->query === null) {
                return;
            }

            $this->queryObject->setList(QueryList::fromString($this->url->query));
        } elseif ($name === 'password') {
            if ($this->url->cannotHaveUsernamePasswordPort()) {
                return;
            }

            $this->url->setPassword($input);
        } elseif ($name === 'pathname') {
            if ($this->url->path->isOpaque()) {
                // Terminate these steps
                return;
            }

            $this->url->path = new PathList();
            $parser->parse($input, null, null, $this->url, ParserState::PATH_START);
        } elseif ($name === 'port') {
            if ($this->url->cannotHaveUsernamePasswordPort()) {
                return;
            }

            if ($value === '') {
                $this->url->port = null;

                return;
            }

            $parser->parse($input, null, null, $this->url, ParserState::PORT);
        } elseif ($name === 'protocol') {
            $parser->parse($input->append(':'), null, null, $this->url, ParserState::SCHEME_START);
        } elseif ($name === 'search') {
            if ($value === '') {
                $this->url->query = null;
                $this->queryObject->setList(new QueryList());
                $this->url->path->potentiallyStripTrailingSpaces($this->url);

                return;
            }

            if ($input->startsWith('?')) {
                $input = $input->substr(1);
            }

            $this->url->query = '';
            $parser->parse($input, null, null, $this->url, ParserState::QUERY);
            $this->queryObject->setList(QueryList::fromString((string) $input));
        } elseif ($name === 'username') {
            if ($this->url->cannotHaveUsernamePasswordPort()) {
                return;
            }

            $this->url->setUsername($input);
        } else {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid property.', $name));
        }
    }

    public function __toString(): string
    {
        return $this->url->serializeURL();
    }
}
