<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;

/**
 * @see https://url.spec.whatwg.org/#special-authority-ignore-slashes-state
 */
class SpecialAuthorityIgnoreSlashesState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If c is neither U+002F (/) nor U+005C (\), then set state to authority state and decrease pointer by 1.
        if ($codePoint !== '/' && $codePoint !== '\\') {
            $context->state = ParserState::AUTHORITY;
            $context->iter->prev();

            return StatusCode::OK;
        }

        // 2. Otherwise, validation error.
        ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('special-scheme-missing-following-solidus', [
            'input'  => (string) $context->input,
            'column' => $context->iter->key() + 1,
        ]) : null;

        return StatusCode::OK;
    }
}
