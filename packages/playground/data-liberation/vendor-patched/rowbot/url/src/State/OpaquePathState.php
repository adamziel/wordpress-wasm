<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;

/**
 * @see https://url.spec.whatwg.org/#cannot-be-a-base-url-path-state
 */
class OpaquePathState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        $percentEncoder = null;

        do {
            // 1. If c is U+003F (?), then set url’s query to the empty string and state to query state.
            if ($codePoint === '?') {
                $context->url->query = '';
                $context->state = ParserState::QUERY;

                break;
            }

            // 2. Otherwise, if c is U+0023 (#), then set url’s fragment to the empty string and state to fragment state.
            if ($codePoint === '#') {
                $context->url->fragment = '';
                $context->state = ParserState::FRAGMENT;

                break;
            }

            // 3. Otherwise:
            // 3.1. If c is not the EOF code point, not a URL code point, and not U+0025 (%), validation error.
            if ($codePoint !== CodePoint::EOF && $codePoint !== '%' && !CodePoint::isUrlCodePoint($codePoint)) {
                // Validation error.
                ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('invalid-URL-unit', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;

            // 3.2. If c is U+0025 (%) and remaining does not start with two ASCII hex digits, validation error.
            } elseif (
                $codePoint === '%'
                && !$context->input->substr($context->iter->key() + 1)->startsWithTwoAsciiHexDigits()
            ) {
                // Validation error.
                ($nullsafeVariable2 = $context->logger) ? $nullsafeVariable2->notice('invalid-URL-unit', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;
            }

            // 3.3. If c is not the EOF code point, UTF-8 percent-encode c using the C0 control percent-encode set and
            // append the result to url’s path.
            if ($codePoint !== CodePoint::EOF) {
                $percentEncoder = $percentEncoder ?? new PercentEncoder();
                $context->url->path->first()->append($percentEncoder->percentEncodeAfterEncoding(
                    'utf-8',
                    $codePoint,
                    EncodeSet::C0_CONTROL
                ));
            }

            $context->iter->next();
            $codePoint = $context->iter->current();
        } while ($codePoint !== CodePoint::EOF);

        return StatusCode::OK;
    }
}
