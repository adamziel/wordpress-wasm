<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\String\CodePoint;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;

/**
 * @see https://url.spec.whatwg.org/#fragment-state
 */
class FragmentState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        $buffer = '';

        // 1. If c is not the EOF code point, then:
        while ($codePoint !== CodePoint::EOF) {
            // 1.1. If c is not a URL code point and not U+0025 (%), validation error.
            if ($codePoint !== '%' && !CodePoint::isUrlCodePoint($codePoint)) {
                // Validation error.
                ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('invalid-URL-unit', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;

            // 1.2. If c is U+0025 (%) and remaining does not start with two ASCII hex digits, validation error.
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

            $buffer .= $codePoint;
            $context->iter->next();
            $codePoint = $context->iter->current();
        }

        // 1.3. UTF-8 percent-encode c using the fragment percent-encode set and append the result to url’s fragment.
        $percentEncoder = new PercentEncoder();
        $context->url->fragment .= $percentEncoder->percentEncodeAfterEncoding('utf-8', $buffer, EncodeSet::FRAGMENT);

        return StatusCode::OK;
    }
}
