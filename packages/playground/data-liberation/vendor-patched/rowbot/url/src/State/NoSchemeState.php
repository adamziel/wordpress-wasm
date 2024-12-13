<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;

/**
 * @see https://url.spec.whatwg.org/#no-scheme-state
 */
class NoSchemeState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If base is null, or base has an opaque path and c is not U+0023 (#), validation error, return failure.
        if ($context->base === null || ($context->base->path->isOpaque() && $codePoint !== '#')) {
            // Validation error. Return failure.
            ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->warning('missing-scheme-non-relative-URL', [
                'input'  => (string) $context->input,
                'column' => $context->iter->key() + 1,
            ]) : null;

            return StatusCode::FAILURE;
        }

        // 2. Otherwise, if base has an opaque path and c is U+0023 (#), set url’s scheme to base’s scheme, url’s path
        // to base’s path, url’s query to base’s query, url’s fragment to the empty string, and set state to fragment
        // state.
        if ($context->base->path->isOpaque() && $codePoint === '#') {
            $context->url->scheme = clone $context->base->scheme;
            $context->url->path = clone $context->base->path;
            $context->url->query = $context->base->query;
            $context->url->fragment = '';
            $context->state = ParserState::FRAGMENT;

            return StatusCode::OK;
        }

        // 3. Otherwise, if base’s scheme is not "file", set state to relative state and decrease pointer by 1.
        if (!$context->base->scheme->isFile()) {
            $context->state = ParserState::RELATIVE;
            $context->iter->prev();

            return StatusCode::OK;
        }

        // 4. Otherwise, set state to file state and decrease pointer by 1.
        $context->state = ParserState::FILE;
        $context->iter->prev();

        return StatusCode::OK;
    }
}
