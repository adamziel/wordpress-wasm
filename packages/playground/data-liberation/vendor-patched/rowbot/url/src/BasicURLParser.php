<?php

declare(strict_types=1);

namespace Rowbot\URL;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Rowbot\URL\State\StatusCode;
use Rowbot\URL\String\StringBuffer;
use Rowbot\URL\String\USVStringInterface;

use function mb_strlen;
use function strlen;
use function substr;

use const PREG_OFFSET_CAPTURE;

class BasicURLParser implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * The parser can parse both absolute and relative URLs. If a relative URL is given, a base URL must also be given
     * so that an absolute URL can be resolved. It can also parse individual parts of a URL when the default starting
     * state is overridden, however, a previously parsed URL record object must be provided in this case.
     *
     * @see https://url.spec.whatwg.org/#concept-basic-url-parser
     *
     * @param \Rowbot\URL\String\USVStringInterface $input            A UTF-8 encoded string consisting of only scalar
     *                                                                values, excluding surrogates.
     * @param \Rowbot\URL\URLRecord|null            $base             (optional) This represents the base URL, which in
     *                                                                most cases, is the document's URL, it may also be
     *                                                                a node's base URI or whatever base URL you wish to
     *                                                                resolve relative URLs against. Default is null.
     * @param string|null                           $encodingOverride (optional) Overrides the default ouput encoding,
     *                                                                which is UTF-8. This option exists solely for the
     *                                                                use of the HTML specification and should never be
     *                                                                changed.
     * @param \Rowbot\URL\URLRecord|null            $url              (optional) This represents an existing URL record
     *                                                                object that should be modified based on the input
     *                                                                URL and optional base URL. Default is null.
     * @param \Rowbot\URL\ParserState|null          $stateOverride    (optional) An object implementing the
     *                                                                \Rowbot\URL\ParserState interface that overrides
     *                                                                the default start state, which is the Scheme Start
     *                                                                State. Default is null.
     * @return \Rowbot\URL\URLRecord|false
     * @param ?\Rowbot\URL\ParserState::* $stateOverride
     */
    public function parse(
        USVStringInterface $input,
        ?URLRecord $base = null,
        ?string $encodingOverride = null,
        ?URLRecord $url = null,
        ?string $stateOverride = null
    ) {
        $count = 0;

        if ($url === null) {
            $url = new URLRecord();
            $originalInput = $input;
            $input = $input->replaceRegex('/^[\x00-\x20]+|[\x00-\x20]+$/u', '', -1, $count);

            if ($count !== 0) {
                // Validation error.
                ($nullsafeVariable1 = $this->logger) ? $nullsafeVariable1->notice('invalid-URL-unit', [
                    'input' => (string) $originalInput,
                    'column_range' => (static function () use ($originalInput): array {
                        $originalInput->matches('/^[\x00-\x20]+|[\x00-\x20]+$/u', $matches, PREG_OFFSET_CAPTURE);

                        if ($matches[0][1] === 0) {
                            return [1, strlen($matches[0][0])];
                        }

                        return [$originalInput->length() - strlen($matches[0][0]) + 1, $originalInput->length()];
                    })(),
                ]) : null;
            }
        }

        $originalInput = $input;
        $input = $input->replaceRegex('/[\x09\x0A\x0D]+/u', '', -1, $count);

        if ($count !== 0) {
            // Validation error.
            ($nullsafeVariable2 = $this->logger) ? $nullsafeVariable2->notice('invalid-URL-unit', [
                'input' => (string) $originalInput,
                'column_range' => (static function () use ($originalInput): array {
                    $originalInput->matches('/[\x09\x0A\x0D]+/u', $matches, PREG_OFFSET_CAPTURE);
                    $start = mb_strlen(substr((string) $originalInput, 0, $matches[0][1]), 'utf-8');

                    return [$start + 1, $start + strlen($matches[0][0])];
                })(),
            ]) : null;
        }

        $iter = $input->getIterator();
        $iter->rewind();
        // length + imaginary eof character
        $length = $input->length() + 1;
        $buffer = new StringBuffer();
        $context = new ParserContext(
            $input,
            $iter,
            $buffer,
            $url,
            $base,
            $stateOverride,
            $encodingOverride,
            $this->logger
        );

        do {
            $handler = ParserState::createHandlerFor($context->state);
            $status = $handler->handle($context, $iter->current());

            if ($status === StatusCode::CONTINUE) {
                $status = StatusCode::OK;

                continue;
            }

            $iter->next();
        } while ($status === StatusCode::OK && $iter->key() < $length);

        switch ($status) {
            case StatusCode::FAILURE:
                return false;
            default:
                return $url;
        }
    }
}
