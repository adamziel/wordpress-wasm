<?php

declare(strict_types=1);

namespace Rowbot\URL\Component\Host;

use ReflectionClass;
use ReflectionClassConstant;
use Rowbot\Idna\Idna;
use Rowbot\URL\ParserContext;
use Rowbot\URL\String\CodePoint;
use Rowbot\URL\String\EncodeSet;
use Rowbot\URL\String\PercentEncoder;
use Rowbot\URL\String\USVStringInterface;

use function array_filter;
use function assert;
use function mb_strcut;
use function mb_strlen;
use function rawurldecode;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;
use const PREG_OFFSET_CAPTURE;

/**
 * @see https://url.spec.whatwg.org/#concept-host-parser
 */
class HostParser
{
    /**
     * @see https://url.spec.whatwg.org/#forbidden-host-code-point
     * @see https://url.spec.whatwg.org/#forbidden-domain-code-point
     */
    private const FORBIDDEN_HOST_CODEPOINTS = '\x00\x09\x0A\x0D\x20#\/:<>?@[\\\\\]^|';
    private const FORBIDDEN_DOMAIN_CODEPOINTS = self::FORBIDDEN_HOST_CODEPOINTS . '\x01-\x1F%\x7F';

    private const UNICODE_IDNA_OPTIONS = [
        'CheckHyphens'            => false,
        'CheckBidi'               => true,
        'CheckJoiners'            => true,
        'UseSTD3ASCIIRules'       => false,
        'Transitional_Processing' => false,
    ];

    /**
     * Parses a host string. The string could represent a domain, IPv4 or IPv6 address, or an opaque host.
     *
     * @param bool $isOpaque (optional) Whether or not the URL has a special scheme.
     *
     * @return \Rowbot\URL\Component\Host\HostInterface|false The returned Host can never be a null host.
     */
    public function parse(
        ParserContext $context,
        USVStringInterface $input,
        bool $isOpaque = false
    ) {
        if ($input->startsWith('[')) {
            if (!$input->endsWith(']')) {
                // Validation error.
                ($nullsafeVariable1 = $context->logger) ? $nullsafeVariable1->warning('IPv6-unclosed', [
                    'input'  => (string) $context->input,
                    'column' => $context->iter->key() + 2,
                ]) : null;

                return false;
            }

            return IPv6AddressParser::parse($context, $input->substr(1, -1));
        }

        if ($isOpaque) {
            return $this->parseOpaqueHost($context, $input);
        }

        assert(!$input->isEmpty());
        $domain = rawurldecode((string) $input);
        $asciiDomain = $this->domainToAscii($context, $domain, false);

        if ($asciiDomain === false) {
            return false;
        }

        if ($asciiDomain->matches('/[' . self::FORBIDDEN_DOMAIN_CODEPOINTS . ']/u', $matches, PREG_OFFSET_CAPTURE)) {
            // Validation error.
            ($nullsafeVariable2 = $context->logger) ? $nullsafeVariable2->warning('domain-invalid-code-point', [
                'input'  => (string) $asciiDomain,
                'column' => mb_strlen(mb_strcut((string) $asciiDomain, 0, $matches[0][1], 'utf-8'), 'utf-8') + 1,
                'unicode_domain' => Idna::toUnicode((string) $asciiDomain, self::UNICODE_IDNA_OPTIONS)->getDomain(),
            ]) : null;

            return false;
        }

        if (IPv4AddressParser::endsInIPv4Number($asciiDomain)) {
            return IPv4AddressParser::parse($context, $asciiDomain);
        }

        return $asciiDomain;
    }

    /**
     * @see https://url.spec.whatwg.org/#concept-domain-to-ascii
     * @return \Rowbot\URL\Component\Host\StringHost|false
     */
    private function domainToAscii(ParserContext $context, string $domain, bool $beStrict)
    {
        // 1. Let result be the result of running Unicode ToASCII with domain_name set to domain, UseSTD3ASCIIRules set
        // to beStrict, CheckHyphens set to false, CheckBidi set to true, CheckJoiners set to true,
        // Transitional_Processing set to false, and VerifyDnsLength set to beStrict.
        $result = Idna::toAscii($domain, [
            'CheckHyphens'            => false,
            'CheckBidi'               => true,
            'CheckJoiners'            => true,
            'UseSTD3ASCIIRules'       => $beStrict,
            'Transitional_Processing' => false,
            'VerifyDnsLength'         => $beStrict,
        ]);
        $convertedDomain = $result->getDomain();

        // 2. If result is a failure value, validation error, return failure.
        // 3. If result is the empty string, validation error, return failure.
        if ($convertedDomain === '' || $result->hasErrors()) {
            // Validation error.
            ($nullsafeVariable3 = $context->logger) ? $nullsafeVariable3->warning('domain-to-ASCII', [
                'input'        => $domain,
                'column_range' => [1, mb_strlen($domain, 'utf-8')],
                'idn_errors'   => $this->enumerateIdnaErrors($result->getErrors()),
                'unicode_domain' => Idna::toUnicode($domain, self::UNICODE_IDNA_OPTIONS)->getDomain(),
            ]) : null;

            return false;
        }

        // 4. Return result.
        return new StringHost($convertedDomain);
    }

    /**
     * Parses an opaque host.
     *
     * @see https://url.spec.whatwg.org/#concept-opaque-host-parser
     * @return \Rowbot\URL\Component\Host\HostInterface|false
     */
    private function parseOpaqueHost(ParserContext $context, USVStringInterface $input)
    {
        if ($input->matches('/[' . self::FORBIDDEN_HOST_CODEPOINTS . ']/u', $matches, PREG_OFFSET_CAPTURE)) {
            // Validation error.
            ($nullsafeVariable4 = $context->logger) ? $nullsafeVariable4->warning('host-invalid-code-point', [
                'input'  => (string) $input,
                'column' => mb_strlen(mb_strcut((string) $input, 0, $matches[0][1], 'utf-8'), 'utf-8') + 1,
            ]) : null;

            return false;
        }

        foreach ($input as $i => $codePoint) {
            if ($codePoint !== '%' && !CodePoint::isUrlCodePoint($codePoint)) {
                // Validation error.
                ($nullsafeVariable5 = $context->logger) ? $nullsafeVariable5->notice('invalid-URL-unit', [
                    'input'  => (string) $input,
                    'column' => $i,
                ]) : null;
            } elseif ($codePoint === '%' && !$input->substr($i + 1)->startsWithTwoAsciiHexDigits()) {
                // Validation error.
                ($nullsafeVariable6 = $context->logger) ? $nullsafeVariable6->notice('invalid-URL-unit', [
                    'input'  => (string) $input,
                    'column' => $i,
                ]) : null;
            }
        }

        $percentEncoder = new PercentEncoder();
        $output = $percentEncoder->percentEncodeAfterEncoding('utf-8', (string) $input, EncodeSet::C0_CONTROL);

        return new StringHost($output);
    }

    /**
     * @return list<string>
     */
    private function enumerateIdnaErrors(int $bitmask): array
    {
        $reflection = new ReflectionClass(Idna::class);
        $errorConstants = array_filter(
            $reflection->getConstants(ReflectionClassConstant::IS_PUBLIC),
            static function (string $name) : bool {
                return strncmp($name, 'ERROR_', strlen('ERROR_')) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );
        $errors = [];

        foreach ($errorConstants as $name => $value) {
            if (($value & $bitmask) !== 0) {
                $errors[] = $name;
            }
        }

        return $errors;
    }
}
