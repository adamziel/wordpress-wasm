<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\Component\PathSegment;
use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;
use Rowbot\URL\String\CodePoint;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;

/**
 * @see https://url.spec.whatwg.org/#path-state
 */
class PathState implements State
{
    /**
     * @see https://url.spec.whatwg.org/#double-dot-path-segment
     */
    private const DOUBLE_DOT_SEGMENT = [
        '..'     => '',
        '.%2e'   => '',
        '.%2E'   => '',
        '%2e.'   => '',
        '%2E.'   => '',
        '%2e%2e' => '',
        '%2E%2E' => '',
        '%2e%2E' => '',
        '%2E%2e' => '',
    ];

    /**
     * @see https://url.spec.whatwg.org/#single-dot-path-segment
     */
    private const SINGLE_DOT_SEGMENT = [
        '.'   => '',
        '%2e' => '',
        '%2E' => '',
    ];

    public function handle(ParserContext $context, string $codePoint)
    {
        $percentEncoder = null;

        do {
            // 1. If one of the following is true:
            //      - c is the EOF code point or U+002F (/)
            //      - url is special and c is U+005C (\)
            //      - state override is not given and c is U+003F (?) or U+0023 (#)
            if (
                $codePoint === CodePoint::EOF
                || $codePoint === '/'
                || ($context->url->scheme->isSpecial() && $codePoint === '\\')
                || (!$context->isStateOverridden() && ($codePoint === '?' || $codePoint === '#'))
            ) {
                $urlIsSpecial = $context->url->scheme->isSpecial();

                // 1.1. If url is special and c is U+005C (\), validation error.
                if ($urlIsSpecial && $codePoint === '\\') {
                    // Validation error.
                    ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('invalid-reverse-solidus', [
                        'input'  => (string) $context->input,
                        'column' => $context->iter->key() + 1,
                    ]) : null;
                }

                $stringBuffer = (string) $context->buffer;

                // 1.2. If buffer is a double-dot path segment, then:
                if (isset(self::DOUBLE_DOT_SEGMENT[$stringBuffer])) {
                    // 1.2.1. Shorten url’s path.
                    $context->url->path->shorten($context->url->scheme);

                    // 1.2.2. If neither c is U+002F (/), nor url is special and c is U+005C (\), append the empty string
                    // to url’s path.
                    if ($codePoint !== '/' && !($urlIsSpecial && $codePoint === '\\')) {
                        $context->url->path->push(new PathSegment());
                    }

                // 1.3. Otherwise, if buffer is a single-dot path segment and if neither c is U+002F (/), nor url is special
                // and c is U+005C (\), append the empty string to url’s path.
                } elseif (
                    isset(self::SINGLE_DOT_SEGMENT[$stringBuffer])
                    && $codePoint !== '/'
                    && !($urlIsSpecial && $codePoint === '\\')
                ) {
                    $context->url->path->push(new PathSegment());

                // 1.4. Otherwise, if buffer is not a single-dot path segment, then:
                } elseif (!isset(self::SINGLE_DOT_SEGMENT[$stringBuffer])) {
                    // 1.4.1. If url’s scheme is "file", url’s path is empty, and buffer is a Windows drive letter, then
                    // replace the second code point in buffer with U+003A (:).
                    if (
                        $context->url->scheme->isFile()
                        && $context->url->path->isEmpty()
                        && $context->buffer->isWindowsDriveLetter()
                    ) {
                        // This is a (platform-independent) Windows drive letter quirk.
                        $context->buffer->setCodePointAt(1, ':');
                    }

                    // 1.4.2. Append buffer to url’s path.
                    $context->url->path->push($context->buffer->toPath());
                }

                // 1.5. Set buffer to the empty string.
                $context->buffer->clear();

                // 1.6. If c is U+003F (?), then set url’s query to the empty string and state to query state.
                if ($codePoint === '?') {
                    $context->url->query = '';
                    $context->state = ParserState::QUERY;

                // If c is U+0023 (#), then set url’s fragment to the empty string and state to fragment state.
                } elseif ($codePoint === '#') {
                    $context->url->fragment = '';
                    $context->state = ParserState::FRAGMENT;
                }

                return StatusCode::OK;
            }

            // 2. Otherwise, run these steps:
            // 2.1. If c is not a URL code point and not U+0025 (%), validation error.
            if ($codePoint !== '%' && !CodePoint::isUrlCodePoint($codePoint)) {
                // Validation error
                ($nullsafeVariable2 = $context->logger) ? $nullsafeVariable2->notice('invalid-URL-unit', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;

            // 2.2. If c is U+0025 (%) and remaining does not start with two ASCII hex digits, validation error.
            } elseif (
                $codePoint === '%'
                && !$context->input->substr($context->iter->key() + 1)->startsWithTwoAsciiHexDigits()
            ) {
                // Validation error
                ($nullsafeVariable3 = $context->logger) ? $nullsafeVariable3->notice('invalid-URL-unit', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;
            }

            // 2.3. UTF-8 percent-encode c using the path percent-encode set and append the result to buffer.
            $percentEncoder = $percentEncoder ?? new PercentEncoder();
            $context->buffer->append($percentEncoder->percentEncodeAfterEncoding('utf-8', $codePoint, EncodeSet::PATH));
            $context->iter->next();
            $codePoint = $context->iter->current();
        } while (true);
    }
}
