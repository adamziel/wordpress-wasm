<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\Component\Host\HostParser;
use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;

/**
 * @see https://url.spec.whatwg.org/#host-state
 */
abstract class AbstractHostState implements State
{
    /**
     * @var bool
     */
    private $isBracketOpen;

    public function __construct()
    {
        $this->isBracketOpen = false;
    }

    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If state override is given and url’s scheme is "file", then decrease pointer by 1 and set state to file
        // host state.
        if ($context->isStateOverridden() && $context->url->scheme->isFile()) {
            $context->iter->prev();
            $context->state = ParserState::FILE_HOST;

            return StatusCode::OK;
        }

        do {
            // 2. Otherwise, if c is U+003A (:) and insideBrackets is false, then:
            if ($codePoint === ':' && !$this->isBracketOpen) {
                // 2.1. If buffer is the empty string, validation error, return failure.
                if ($context->buffer->isEmpty()) {
                    // Validation error. Return failure.
                    ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->warning('host-missing', [
                        'input'  => (string) $context->input,
                        'column' => $context->iter->key() + 1,
                    ]) : null;

                    return StatusCode::FAILURE;
                }

                // 2.2. If state override is given and state override is hostname state, then return.
                if ($context->isOverrideStateHostname()) {
                    return StatusCode::BREAK;
                }

                // 2.3. Let host be the result of host parsing buffer with url is not special.
                $parser = new HostParser();
                $host = $parser->parse($context, $context->buffer->toUtf8String(), !$context->url->scheme->isSpecial());

                // 2.4. If host is failure, then return failure.
                if ($host === false) {
                    return StatusCode::FAILURE;
                }

                // 5. Set url’s host to host, buffer to the empty string, and state to port state.
                $context->url->host = $host;
                $context->buffer->clear();
                $context->state = ParserState::PORT;

                return StatusCode::OK;
            }

            // 3. Otherwise, if one of the following is true:
            //      - c is the EOF code point, U+002F (/), U+003F (?), or U+0023 (#)
            //      - url is special and c is U+005C (\)
            if (
                (
                    $codePoint === CodePoint::EOF
                    || $codePoint === '/'
                    || $codePoint === '?'
                    || $codePoint === '#'
                )
                || ($context->url->scheme->isSpecial() && $codePoint === '\\')
            ) {
                // then decrease pointer by 1, and then:
                $context->iter->prev();

                // 3.1. If url is special and buffer is the empty string, validation error, return failure.
                if ($context->url->scheme->isSpecial() && $context->buffer->isEmpty()) {
                    // Validation error. Return failure.
                    ($nullsafeVariable2 = $context->logger) ? $nullsafeVariable2->warning('host-missing', [
                        'input'  => (string) $context->input,
                        'column' => $context->iter->key() + 2, // Add 2 since we called ->prev() above
                    ]) : null;

                    return StatusCode::FAILURE;
                }

                // 3.2. Otherwise, if state override is given, buffer is the empty string, and either url includes
                // credentials or url’s port is non-null, return.
                if (
                    $context->isStateOverridden()
                    && $context->buffer->isEmpty()
                    && ($context->url->includesCredentials() || $context->url->port !== null)
                ) {
                    return StatusCode::BREAK;
                }

                // 3.3. Let host be the result of host parsing buffer with url is not special.
                $parser = new HostParser();
                $host = $parser->parse($context, $context->buffer->toUtf8String(), !$context->url->scheme->isSpecial());

                // 3.4. If host is failure, then return failure.
                if ($host === false) {
                    return StatusCode::FAILURE;
                }

                // 3.5. Set url’s host to host, buffer to the empty string, and state to path start state.
                $context->url->host = $host;
                $context->buffer->clear();
                $context->state = ParserState::PATH_START;

                // 3.6. If state override is given, then return.
                if ($context->isStateOverridden()) {
                    return StatusCode::BREAK;
                }

                return StatusCode::OK;
            }

            // 4. Otherwise:
            // 4.1. If c is U+005B ([), then set insideBrackets to true.
            if ($codePoint === '[') {
                $this->isBracketOpen = true;

            // 4.2. If c is U+005D (]), then set insideBrackets to false.
            } elseif ($codePoint === ']') {
                $this->isBracketOpen = false;
            }

            // 4.3. Append c to buffer.
            $context->buffer->append($codePoint);
            $context->iter->next();
            $codePoint = $context->iter->current();
        } while (true);
    }
}
