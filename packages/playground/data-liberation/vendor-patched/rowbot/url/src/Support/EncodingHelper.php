<?php

declare(strict_types=1);

namespace Rowbot\URL\Support;

use function strtolower;

class EncodingHelper
{
    /**
     * @see https://encoding.spec.whatwg.org/#get-an-output-encoding
     */
    public static function getOutputEncoding(?string $encoding): ?string
    {
        if ($encoding === null) {
            return null;
        }

        $encoding = strtolower($encoding);

        if (
            $encoding === 'replacement'
            || $encoding === 'utf-16'
            || $encoding === 'utf-16le'
            || $encoding === 'utf-16be'
        ) {
            return 'utf-8';
        }

        // We could validate the encoding against the list of valid encodings found at
        // {@link https://encoding.spec.whatwg.org/#concept-encoding-get} and the list of supported
        // encodings on the PHP installation. However, I'm not convinced it would be worth the
        // effort since overriding the encoding is a feature designed only for use in the HTML
        // specification.
        return $encoding;
    }
}
