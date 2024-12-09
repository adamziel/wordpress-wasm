<?php

declare(strict_types=1);

namespace Rowbot\URL\State;

use Rowbot\URL\ParserContext;
use Rowbot\URL\ParserState;

/**
 * @see https://url.spec.whatwg.org/#file-slash-state
 */
class FileSlashState implements State
{
    public function handle(ParserContext $context, string $codePoint)
    {
        // 1. If c is U+002F (/) or U+005C (\), then:
        if ($codePoint === '/' || $codePoint === '\\') {
            // 1.2. If c is U+005C (\), validation error.
            if ($codePoint === '\\') {
                // Validation error.
                ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->notice('invalid-reverse-solidus', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 1,
                ]) : null;
            }

            // 1.2. Set state to file host state.
            $context->state = ParserState::FILE_HOST;

            return StatusCode::OK;
        }

        // 2. Otherwise:
        // 2.1. If base is non-null and base’s scheme is "file", then:
        if ($context->base !== null && $context->base->scheme->isFile()) {
            // 2.1.1. Set url’s host to base’s host.
            $context->url->host = clone $context->base->host;
            $path = $context->base->path->first();

            // 2.1.2. If the substring from pointer in input does not start with a Windows drive letter and base’s
            // path[0] is a normalized Windows drive letter, then append base’s path[0] to url’s path.
            if (
                !$context->input->substr($context->iter->key())->startsWithWindowsDriveLetter()
                && $path->isNormalizedWindowsDriveLetter()
            ) {
                // This is a (platform-independent) Windows drive letter quirk. Both url’s and
                // base’s host are null under these conditions and therefore not copied.
                $context->url->path->push($path);
            }
        }

        // 2.2. Set state to path state, and decrease pointer by 1.
        $context->state = ParserState::PATH;
        $context->iter->prev();

        return StatusCode::OK;
    }
}
