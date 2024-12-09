<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;

use function assert;

/**
 * @see https://url.spec.whatwg.org/#relative-state
 */
class RelativeState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. Assert: base’s scheme is not "file".
        assert($context->base !== null && !$context->base->scheme->isFile());

        // 2. Set url’s scheme to base’s scheme.
        $context->url->scheme = clone $context->base->scheme;

        // 3. If c is U+002F (/), then set state to relative slash state.
        if ($codePoint === '/') {
            $context->state = ParserState::RELATIVE_SLASH;

            return StatusCode::OK;
        }

        // 4. Otherwise, if url is special and c is U+005C (\), validation error, set state to relative slash state.
        if ($context->url->scheme->isSpecial() && $codePoint === '\\') {
            // Validation error
            ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('invalid-reverse-solidus', [
                'input'  => (string) $context->input,
                'column' => $context->iter->key() + 1,
            ]) : null;
            $context->state = ParserState::RELATIVE_SLASH;

            return StatusCode::OK;
        }

        // 5. Otherwise:
        // 5.1. Set url’s username to base’s username, url’s password to base’s password, url’s host to base’s host,
        // url’s port to base’s port, url’s path to a clone of base’s path, and url’s query to base’s query.
        $context->url->username = $context->base->username;
        $context->url->password = $context->base->password;
        $context->url->host = clone $context->base->host;
        $context->url->port = $context->base->port;
        $context->url->path = clone $context->base->path;
        $context->url->query = $context->base->query;

        // 5.2. If c is U+003F (?), then set url’s query to the empty string, and state to query state.
        if ($codePoint === '?') {
            $context->url->query = '';
            $context->state = ParserState::QUERY;

            return StatusCode::OK;
        }

        // 5.3. Otherwise, if c is U+0023 (#), set url’s fragment to the empty string and state to fragment state.
        if ($codePoint === '#') {
            $context->url->fragment = '';
            $context->state = ParserState::FRAGMENT;

            return StatusCode::OK;
        }

        // 5.4 Otherwise, if c is not the EOF code point:
        if ($codePoint === CodePoint::EOF) {
            return StatusCode::OK;
        }

        // 5.4.1. Set url’s query to null.
        $context->url->query = null;

        // 5.4.2. Shorten url’s path.
        $context->url->path->shorten($context->url->scheme);

        // 5.4.3. Set state to path state and decrease pointer by 1.
        $context->state = ParserState::PATH;
        $context->iter->prev();

        return StatusCode::OK;
    }
}
