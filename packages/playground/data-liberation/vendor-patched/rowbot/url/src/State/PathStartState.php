<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\Component\PathSegment;
use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;

/**
 * @see https://url.spec.whatwg.org/#path-start-state
 */
class PathStartState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If url is special, then:
        if ($context->url->scheme->isSpecial()) {
            // 1.1. If c is U+005C (\), validation error.
            if ($codePoint === '\\') {
                // Validation error.
                ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('invalid-reverse-solidus', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;
            }

            // 1.2. Set state to path state.
            $context->state = ParserState::PATH;

            // 1.3. If c is neither U+002F (/) nor U+005C (\), then decrease pointer by 1.
            if ($codePoint !== '/' && $codePoint !== '\\') {
                $context->iter->prev();
            }

            return StatusCode::OK;
        }

        // 2. Otherwise, if state override is not given and c is U+003F (?), set url’s query to the empty string and
        // state to query state.
        if (!$context->isStateOverridden() && $codePoint === '?') {
            $context->url->query = '';
            $context->state = ParserState::QUERY;

            return StatusCode::OK;
        }

        // 3. Otherwise, if state override is not given and c is U+0023 (#), set url’s fragment to the empty string and
        // state to fragment state.
        if (!$context->isStateOverridden() && $codePoint === '#') {
            $context->url->fragment = '';
            $context->state = ParserState::FRAGMENT;

            return StatusCode::OK;
        }

        // 4. Otherwise, if c is not the EOF code point:
        if ($codePoint !== CodePoint::EOF) {
            // 4.1. Set state to path state.
            $context->state = ParserState::PATH;

            // 4.2. If c is not U+002F (/), then decrease pointer by 1.
            if ($codePoint !== '/') {
                $context->iter->prev();
            }

            return StatusCode::OK;
        }

        // 5. Otherwise, if state override is given and url’s host is null, append the empty string to url’s path.
        if ($context->isStateOverridden() && $context->url->host->isNull()) {
            $context->url->path->push(new PathSegment());
        }

        return StatusCode::OK;
    }
}
