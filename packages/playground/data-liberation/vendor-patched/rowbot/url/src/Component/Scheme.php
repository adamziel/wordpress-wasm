<?php

declare(strict_types=1);

namespace Rowbot\URL\Component;

use Stringable;

use function array_key_exists;

class Scheme
{
    /**
     * @see https://url.spec.whatwg.org/#special-scheme
     */
    private const SPECIAL_SCHEMES = [
        'ftp'    => 21,
        'file'   => null,
        'http'   => 80,
        'https'  => 443,
        'ws'     => 80,
        'wss'    => 443,
    ];
    /**
     * @var string
     */
    private $scheme;
    public function __construct(string $scheme = '')
    {
        $this->scheme = $scheme;
    }
    public function isBlob(): bool
    {
        return $this->scheme === 'blob';
    }
    public function isFile(): bool
    {
        return $this->scheme === 'file';
    }
    public function isWebsocket(): bool
    {
        return $this->scheme === 'wss' || $this->scheme === 'ws';
    }
    /**
     * Returns whether or not the string is a special scheme.
     *
     * @see https://url.spec.whatwg.org/#is-special
     */
    public function isSpecial(): bool
    {
        return array_key_exists($this->scheme, self::SPECIAL_SCHEMES);
    }
    public function isDefaultPort(?int $port): bool
    {
        if (!isset(self::SPECIAL_SCHEMES[$this->scheme])) {
            return false;
        }

        return self::SPECIAL_SCHEMES[$this->scheme] === $port;
    }
    public function equals(self $other): bool
    {
        return $this->scheme === $other->scheme;
    }
    public function __toString(): string
    {
        return $this->scheme;
    }
}
