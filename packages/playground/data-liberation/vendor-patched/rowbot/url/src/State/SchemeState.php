<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\Component\OpaquePath;
use Rowbot\URL\Component\PathSegment;
use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;

use function assert;
use function strpbrk;
use function strtolower;

/**
 * @see https://url.spec.whatwg.org/#scheme-state
 */
class SchemeState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If c is an ASCII alphanumeric, U+002B (+), U+002D (-), or U+002E (.), append c, lowercased, to buffer.
        while (
            strpbrk($codePoint, CodePoint::ASCII_ALNUM_MASK) === $codePoint
            || $codePoint === '+'
            || $codePoint === '-'
            || $codePoint === '.'
        ) {
            $context->buffer->append(strtolower($codePoint));
            $context->iter->next();
            $codePoint = $context->iter->current();
        }

        // 2. Otherwise, if c is U+003A (:), then:
        if ($codePoint === ':') {
            $stateIsOverridden = $context->isStateOverridden();
            $bufferIsSpecialScheme = false;
            $candidateScheme = $context->buffer->toScheme();

            // 2.1. If state override is given, then:
            if ($stateIsOverridden) {
                $bufferIsSpecialScheme = $candidateScheme->isSpecial();
                $urlIsSpecial = $context->url->scheme->isSpecial();

                // 2.1.1. If url’s scheme is a special scheme and buffer is not a special scheme, then return.
                // 2.1.2. If url’s scheme is not a special scheme and buffer is a special scheme, then return.
                if ($urlIsSpecial xor $bufferIsSpecialScheme) {
                    return StatusCode::BREAK;
                }

                // 2.1.3. If url includes credentials or has a non-null port, and buffer is "file", then return.
                if (
                    $context->url->includesCredentials()
                    || ($context->url->port !== null && $candidateScheme->isFile())
                ) {
                    return StatusCode::BREAK;
                }

                // 2.1.4. If url’s scheme is "file" and its host is an empty host, then return.
                if ($context->url->scheme->isFile() && $context->url->host->isEmpty()) {
                    return StatusCode::BREAK;
                }
            }

            // 2.2. Set url’s scheme to buffer.
            $context->url->scheme = $candidateScheme;

            // 2.3. If state override is given, then:
            if ($stateIsOverridden) {
                // 2.3.1. If url’s port is url’s scheme’s default port, then set url’s port to null.
                if ($bufferIsSpecialScheme && $context->url->scheme->isDefaultPort($context->url->port)) {
                    $context->url->port = null;
                }

                // 2.3.2. Return.
                return StatusCode::BREAK;
            }

            // 2.4. Set buffer to the empty string.
            $context->buffer->clear();
            $urlIsSpecial = $context->url->scheme->isSpecial();

            // 2.5. If url’s scheme is "file", then:
            if ($context->url->scheme->isFile()) {
                // 2.5.1. If remaining does not start with "//", validation error.
                if ($context->iter->peek(2) !== '//') {
                    // Validation error.
                    ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('special-scheme-missing-following-solidus', [
                        'input'  => (string) $context->input,
                        'column' => $context->iter->key() + 2,
                    ]) : null;
                }

                // 2.5.2. Set state to file state.
                $context->state = ParserState::FILE;

            // 2.6. Otherwise, if url is special, base is non-null, and base’s scheme is equal to url’s scheme, set
            // state to special relative or authority state.
            } elseif (
                $urlIsSpecial
                && $context->base !== null
                && $context->base->scheme->equals($context->url->scheme)
            ) {
                assert(
                    $context->base->scheme->isSpecial(),
                    'base is special (and therefore does not have an opaque path)'
                );
                $context->state = ParserState::SPECIAL_RELATIVE_OR_AUTHORITY;

            // 2.7. Otherwise, if url is special, set state to special authority slashes state.
            } elseif ($urlIsSpecial) {
                $context->state = ParserState::SPECIAL_AUTHORITY_SLASHES;

            // 2.8. Otherwise, if remaining starts with an U+002F (/), set state to path or authority state and
            // increase pointer by 1.
            } elseif ($context->iter->peek() === '/') {
                $context->state = ParserState::PATH_OR_AUTHORITY;
                $context->iter->next();

            // 2.9. Otherwise, set url’s path to the empty string and set state to opaque path state.
            } else {
                $context->url->path = new OpaquePath(new PathSegment());
                $context->state = ParserState::OPAQUE_PATH;
            }

            return StatusCode::OK;
        }

        // 3. Otherwise, if state override is not given, set buffer to the empty string, state to no scheme state, and
        // start over (from the first code point in input).
        if (!$context->isStateOverridden()) {
            $context->buffer->clear();
            $context->state = ParserState::NO_SCHEME;

            // Reset the pointer to point at the first code point.
            $context->iter->rewind();

            return StatusCode::CONTINUE;
        }

        // 4. Otherwise, return failure.
        //
        // Note: This indication of failure is used exclusively by the Location object's protocol
        // attribute. Furthermore, the non-failure termination earlier in this state is an
        // intentional difference for defining that attribute.
        return StatusCode::FAILURE;
    }
}
