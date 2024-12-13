<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

use Rowbot\URL\String\Exception\EncodingException;

use function mb_convert_encoding;
use function mb_scrub;
use function mb_substitute_character;
use function sprintf;

class Utf8String extends AbstractUSVString
{
    public static function fromUnsafe(string $input): self
    {
        return new self(self::scrub($input));
    }

    public static function scrub(string $input): string
    {
        $substituteChar = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        $input = mb_scrub($input, 'utf-8');
        mb_substitute_character($substituteChar);

        return $input;
    }

    public static function transcode(string $string, string $toEncoding, string $fromEncoding): string
    {
        $substituteChar = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        $result = mb_convert_encoding($string, $toEncoding, $fromEncoding);
        mb_substitute_character($substituteChar);

        if ($result === false) {
            throw new EncodingException(sprintf(
                'Transcoding from "%s" to "%s" failed.',
                $fromEncoding,
                $toEncoding
            ));
        }

        return $result;
    }
}
