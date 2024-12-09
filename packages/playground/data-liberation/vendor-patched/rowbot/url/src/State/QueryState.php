<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;

/**
 * @see https://url.spec.whatwg.org/#query-state
 */
class QueryState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If encoding is not UTF-8 and one of the following is true:
        //      - url is not special
        //      - url’s scheme is "ws" or "wss"
        // then set encoding to UTF-8.
        if (
            $context->getOutputEncoding() !== 'utf-8'
            && (!$context->url->scheme->isSpecial() || $context->url->scheme->isWebsocket())
        ) {
            $context->setOutputEncoding('utf-8');
        }

        do {
            // 2. If one of the following is true:
            //      - state override is not given and c is U+0023 (#)
            //      - c is the EOF code point
            // then:
            if (!$context->isStateOverridden() && $codePoint === '#' || $codePoint === CodePoint::EOF) {
                // 2.1. Let queryPercentEncodeSet be the special-query percent-encode set if url is special; otherwise the
                // query percent-encode set.
                $queryPercentEncodeSet = $context->url->scheme->isSpecial()
                    ? EncodeSet::SPECIAL_QUERY
                    : EncodeSet::QUERY;

                // 2.2. Percent-encode after encoding, with encoding, buffer, and queryPercentEncodeSet, and append the
                // result to url’s query.
                //
                // NOTE: This operation cannot be invoked code-point-for-code-point due to the stateful ISO-2022-JP encoder.
                $percentEncoder = new PercentEncoder();
                $context->url->query .= $percentEncoder->percentEncodeAfterEncoding(
                    $context->getOutputEncoding(),
                    (string) $context->buffer,
                    $queryPercentEncodeSet
                );

                // 2.3. Set buffer to the empty string.
                $context->buffer->clear();

                // 2.4. If c is U+0023 (#), then set url’s fragment to the empty string and state to fragment state.
                if ($codePoint === '#') {
                    $context->url->fragment = '';
                    $context->state = ParserState::FRAGMENT;
                }

                return StatusCode::OK;
            }

            // 3. Otherwise, if c is not the EOF code point:
            // 3.1. If c is not a URL code point and not U+0025 (%), validation error.
            if ($codePoint !== '%' && !CodePoint::isUrlCodePoint($codePoint)) {
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

            // 3.3. Append c to buffer.
            $context->buffer->append($codePoint);
            $context->iter->next();
            $codePoint = $context->iter->current();
        } while (true);
    }
}
