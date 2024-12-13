<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;

use function strpbrk;

/**
 * @see https://url.spec.whatwg.org/#port-state
 */
class PortState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If c is an ASCII digit, append c to buffer.
        while (strpbrk($codePoint, CodePoint::ASCII_DIGIT_MASK) === $codePoint) {
            $context->buffer->append($codePoint);
            $context->iter->next();
            $codePoint = $context->iter->current();
        }

        // 2. Otherwise, if one of the following is true:
        //      - c is the EOF code point, U+002F (/), U+003F (?), or U+0023 (#)
        //      - url is special and c is U+005C (\)
        //      - state override is given
        if (
            (
                $codePoint === CodePoint::EOF
                || $codePoint === '/'
                || $codePoint === '?'
                || $codePoint === '#'
            )
            || ($context->url->scheme->isSpecial() && $codePoint === '\\')
            || $context->isStateOverridden()
        ) {
            // 2.1. If buffer is not the empty string, then:
            if (!$context->buffer->isEmpty()) {
                // 2.1.1. Let port be the mathematical integer value that is represented by buffer in radix-10 using
                // ASCII digits for digits with values 0 through 9.
                $port = $context->buffer->toInt();

                // 2.1.2. If port is greater than 2 ^ 16 − 1, validation error, return failure.
                if ($port > 2 ** 16 - 1) {
                    // Validation error. Return failure.
                    ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->warning('port-out-of-range', [
                        'input'        => (string) $context->input,
                        'column_range' => [
                            $context->iter->key() - $context->buffer->length() + 1,
                            $context->iter->key(),
                        ],
                    ]) : null;

                    return StatusCode::FAILURE;
                }

                // 2.1.3. Set url’s port to null, if port is url’s scheme’s default port, and to port otherwise.
                if ($context->url->scheme->isSpecial() && $context->url->scheme->isDefaultPort($port)) {
                    $context->url->port = null;
                } else {
                    $context->url->port = $port;
                }

                // 2.1.4. Set buffer to the empty string.
                $context->buffer->clear();
            }

            // 2.2. If state override is given, then return.
            if ($context->isStateOverridden()) {
                return StatusCode::BREAK;
            }

            // 2.3. Set state to path start state and decrease pointer by 1.
            $context->state = ParserState::PATH_START;
            $context->iter->prev();

            return StatusCode::OK;
        }

        // 3. Otherwise, validation error, return failure.
        ($nullsafeVariable2 = $context->logger) ? $nullsafeVariable2->warning('port-invalid', [
            'input'        => (string) $context->input,
            'column_range' => [$context->iter->key() - $context->buffer->length() + 1, $context->iter->key() + 1],
        ]) : null;

        return StatusCode::FAILURE;
    }
}
