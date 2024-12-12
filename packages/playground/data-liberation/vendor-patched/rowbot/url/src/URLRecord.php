<?php

declare(strict_types=1);

namespace Rowbot\URL;

use Rowbot\URL\Component\Host\HostInterface;
use Rowbot\URL\Component\Host\NullHost;
use Rowbot\URL\Component\OpaqueOrigin;
use Rowbot\URL\Component\PathInterface;
use Rowbot\URL\Component\PathList;
use Rowbot\URL\Component\Scheme;
use Rowbot\URL\Component\TupleOrigin;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;
use Rowbot\URL\String\USVStringInterface;

class URLRecord
{
    /**
     * An ASCII string that identifies the type of URL.
     * @var \Rowbot\URL\Component\Scheme
     */
    public $scheme;

    /**
     * An ASCII string identifying a username.
     * @var string
     */
    public $username;

    /**
     * An ASCII string identifying a password.
     * @var string
     */
    public $password;

    /**
     * @var \Rowbot\URL\Component\Host\HostInterface
     */
    public $host;

    /**
     * A 16-bit unsigned integer that identifies a networking port.
     * @var int|null
     */
    public $port;

    /**
     * An ASCII string or a list of zero or more ASCII strings; Initially an empty list.
     * @var \Rowbot\URL\Component\PathInterface
     */
    public $path;

    /**
     * An ASCII string holding data.
     * @var string|null
     */
    public $query;

    /**
     * An ASCII string holding data.
     * @var string|null
     */
    public $fragment;

    public function __construct()
    {
        $this->scheme = new Scheme();
        $this->username = '';
        $this->password = '';
        $this->host = new NullHost();
        $this->port = null;
        $this->path = new PathList();
        $this->query = null;
        $this->fragment = null;
    }

    /**
     * Whether or not a URL can have a username, password, or port set.
     *
     * @see https://url.spec.whatwg.org/#cannot-have-a-username-password-port
     */
    public function cannotHaveUsernamePasswordPort(): bool
    {
        return $this->host->isNull() || $this->host->isEmpty() || $this->scheme->isFile();
    }

    /**
     * Whether or not the URL has a username or password.
     *
     * @see https://url.spec.whatwg.org/#include-credentials
     */
    public function includesCredentials(): bool
    {
        return $this->username !== '' || $this->password !== '';
    }

    /**
     * Computes a URL's origin.
     *
     * @see https://url.spec.whatwg.org/#origin
     */
    public function getOrigin(): Origin
    {
        if ($this->scheme->isBlob()) {
            $parser = new BasicURLParser();
            $url = $parser->parse($this->path->first()->toUtf8String());

            if ($url === false) {
                // Return a new opaque origin
                return new OpaqueOrigin();
            }

            switch ((string) $url->scheme) {
                case 'https':
                case 'http':
                case 'file':
                    return $url->getOrigin();
                default:
                    return new OpaqueOrigin();
            }
        }

        if ($this->scheme->isFile()) {
            // Unfortunate as it is, this is left as an exercise to the
            // reader. When in doubt, return a new opaque origin.
            return new OpaqueOrigin();
        }

        if ($this->scheme->isSpecial()) {
            // Return a tuple consiting of URL's scheme, host, port, and null
            return new TupleOrigin((string) $this->scheme, $this->host, $this->port);
        }

        // Return a new opaque origin.
        return new OpaqueOrigin();
    }

    /**
     * Determines whether two URLs are equal to eachother.
     *
     * @see https://url.spec.whatwg.org/#concept-url-equals
     *
     * @param bool $excludeFragment (optional) determines whether a URL's fragment should be factored into equality.
     */
    public function isEqual(self $otherUrl, bool $excludeFragment = false): bool
    {
        return $this->serializeURL($excludeFragment) === $otherUrl->serializeURL($excludeFragment);
    }

    /**
     * Serializes a URL object.
     *
     * @see https://url.spec.whatwg.org/#concept-url-serializer
     *
     * @param bool $excludeFragment (optional) When specified it will exclude the URL's fragment from being serialized.
     */
    public function serializeURL(bool $excludeFragment = false): string
    {
        $output = $this->scheme . ':';
        $isNullHost = $this->host->isNull();

        if (!$isNullHost) {
            $output .= '//';

            if ($this->username !== '' || $this->password !== '') {
                $output .= $this->username;

                if ($this->password !== '') {
                    $output .= ':' . $this->password;
                }

                $output .= '@';
            }

            $output .= $this->host->getSerializer()->toFormattedString();

            if ($this->port !== null) {
                $output .= ':' . $this->port;
            }
        }

        // 3. If url’s host is null, url does not have an opaque path, url’s path’s size is greater than 1, and url’s
        // path[0] is the empty string, then append U+002F (/) followed by U+002E (.) to output.
        if ($isNullHost && !$this->path->isOpaque() && $this->path->count() > 1 && $this->path->first()->isEmpty()) {
            // NOTE: This prevents web+demo:/.//not-a-host/ or web+demo:/path/..//not-a-host/, when parsed and then
            // serialized, from ending up as web+demo://not-a-host/ (they end up as web+demo:/.//not-a-host/)
            $output .= '/.';
        }

        // 4. Append the result of URL path serializing url to output.
        $output .= $this->path;

        // 5. If url’s query is non-null, append U+003F (?), followed by url’s query, to output.
        if ($this->query !== null) {
            $output .= '?' . $this->query;
        }

        // 6. If exclude fragment is false and url’s fragment is non-null, then append U+0023 (#), followed by url’s
        // fragment, to output.
        if (!$excludeFragment && $this->fragment !== null) {
            $output .= '#' . $this->fragment;
        }

        // 7. Return output.
        return $output;
    }

    /**
     * @see https://url.spec.whatwg.org/#set-the-password
     */
    public function setPassword(USVStringInterface $input): void
    {
        $percentEncoder = new PercentEncoder();
        $this->password = $percentEncoder->percentEncodeAfterEncoding(
            'utf-8',
            (string) $input,
            EncodeSet::USERINFO
        );
    }

    /**
     * @see https://url.spec.whatwg.org/#set-the-username
     */
    public function setUsername(USVStringInterface $input): void
    {
        $percentEncoder = new PercentEncoder();
        $this->username = $percentEncoder->percentEncodeAfterEncoding(
            'utf-8',
            (string) $input,
            EncodeSet::USERINFO
        );
    }

    public function __clone()
    {
        $this->scheme = clone $this->scheme;
        $this->host = clone $this->host;
        $this->path = clone $this->path;
    }
}
