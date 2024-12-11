<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;

/**
 * @see https://url.spec.whatwg.org/#path-or-authority-state
 */
class PathOrAuthorityState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If c is U+002F (/), then set state to authority state.
        if ($codePoint === '/') {
            $context->state = ParserState::AUTHORITY;

            return StatusCode::OK;
        }

        // 2. Otherwise, set state to path state, and decrease pointer by 1.
        $context->state = ParserState::PATH;
        $context->iter->prev();

        return StatusCode::OK;
    }
}
