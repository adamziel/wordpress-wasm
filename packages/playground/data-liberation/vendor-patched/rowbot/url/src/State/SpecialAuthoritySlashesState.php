<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;

/**
 * @see https://url.spec.whatwg.org/#special-authority-slashes-state
 */
class SpecialAuthoritySlashesState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If c is U+002F (/) and remaining starts with U+002F (/), then set state to special authority ignore
        // slashes state and increase pointer by 1.
        if ($codePoint === '/' && $context->iter->peek() === '/') {
            $context->state = ParserState::SPECIAL_AUTHORITY_IGNORE_SLASHES;
            $context->iter->next();

            return StatusCode::OK;
        }

        // 2. Otherwise, validation error, set state to special authority ignore slashes state and decrease pointer
        // by 1.
        $context->state = ParserState::SPECIAL_AUTHORITY_IGNORE_SLASHES;
        ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('special-scheme-missing-following-solidus', [
            'input'  => (string) $context->input,
            'column' => $context->iter->key() + 1,
        ]) : null;
        $context->iter->prev();

        return StatusCode::OK;
    }
}
