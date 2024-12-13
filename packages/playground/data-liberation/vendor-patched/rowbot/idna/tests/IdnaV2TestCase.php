<?php

declare(strict_types=1);

namespace Rowbot\Idna\Test;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Rowbot\Idna\Idna;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function array_map;
use function count;
use function explode;
use function in_array;
use function preg_last_error;
use function preg_match_all;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const PREG_NO_ERROR;

class IdnaV2TestCase extends TestCase
{
    private const ERROR_CODE_MAP = [
        'P1' => Idna::ERROR_DISALLOWED,
        'P4' => [
            Idna::ERROR_EMPTY_LABEL,
            Idna::ERROR_DOMAIN_NAME_TOO_LONG,
            Idna::ERROR_LABEL_TOO_LONG,
            Idna::ERROR_PUNYCODE,
        ],
        'V1' => Idna::ERROR_INVALID_ACE_LABEL,
        'V2' => Idna::ERROR_HYPHEN_3_4,
        'V3' => [Idna::ERROR_LEADING_HYPHEN, Idna::ERROR_TRAILING_HYPHEN],
        'V4' => Idna::ERROR_LABEL_HAS_DOT,
        'V5' => Idna::ERROR_LEADING_COMBINING_MARK,
        'V6' => Idna::ERROR_DISALLOWED,
        // V7 and V8 are handled by C* and B* respectively.
        'A3' => Idna::ERROR_PUNYCODE,
        'A4_1' => Idna::ERROR_DOMAIN_NAME_TOO_LONG,
        'A4_2' => [Idna::ERROR_EMPTY_LABEL, Idna::ERROR_LABEL_TOO_LONG],
        'B1' => Idna::ERROR_BIDI,
        'B2' => Idna::ERROR_BIDI,
        'B3' => Idna::ERROR_BIDI,
        'B4' => Idna::ERROR_BIDI,
        'B5' => Idna::ERROR_BIDI,
        'B6' => Idna::ERROR_BIDI,
        'C1' => Idna::ERROR_CONTEXTJ,
        'C2' => Idna::ERROR_CONTEXTJ,
        // ContextO isn't tested here.
        // 'C3' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C4' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C5' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C6' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C7' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C8' => Idna::ERROR_CONTEXTO_DIGITS,
        // 'C9' => Idna::ERROR_CONTEXTO_DIGITS,
        'X4_2' => Idna::ERROR_EMPTY_LABEL,
        'X3' => Idna::ERROR_EMPTY_LABEL,
    ];

    private const BASE_URI = 'https://www.unicode.org/Public/idna/';
    private const TEST_FILE = 'IdnaTestV2.txt';
    private const CACHE_TTL = 86400 * 7; // 7 DAYS
    private const TEST_DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'data';

    /**
     * @return array<int, array<int, string>>
     */
    public function loadTestData(string $version): array
    {
        $cache = new FilesystemAdapter(
            'unicode-idna-test-data',
            self::CACHE_TTL,
            self::TEST_DATA_DIR
        );
        $testData = $cache->getItem($version);

        if ($testData->isHit()) {
            return $testData->get();
        }

        $client = new Client([
            'base_uri' => self::BASE_URI
        ]);
        $response = $client->request('GET', $version . '/' . self::TEST_FILE);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(sprintf(
                'Got status code %d trying to retrieve %s for Unicode version %s.',
                $response->getStatusCode(),
                self::TEST_FILE,
                $version
            ));
        }

        $data = $this->processResponse($response);
        $testData->set($data);
        $cache->save($testData);

        return $data;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function processResponse(ResponseInterface $response): array
    {
        $output = [];

        foreach (explode("\n", (string) $response->getBody()) as $line) {
            // Ignore empty lines and comments.
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $columns = array_map('\trim', explode(';', $data));
            assert(count($columns) === 7);
            $output[] = $columns;
        }

        return $output;
    }

    /**
     * @param array<int, int|array<int, int>> $inherit
     *
     * @return array<int, int|array<int, int>>
     */
    private function resolveErrorCodes(string $statusCodes, array $inherit, array $ignore): array
    {
        if ($statusCodes === '') {
            return $inherit;
        }

        if ($statusCodes === '[]') {
            return [];
        }

        $matchCount = preg_match_all('/[PVUABCX][0-9](?:_[0-9])?/', $statusCodes, $matches);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException();
        }

        if ($matchCount === 0) {
            throw new RuntimeException();
        }

        $errors = [];

        foreach ($matches[0] as $match) {
            if ($match[0] === 'U' || in_array($match, $ignore, true)) {
                continue;
            }

            if (!isset(self::ERROR_CODE_MAP[$match])) {
                throw new RuntimeException(sprintf('Unhandled error code %s.', $match));
            }

            $errors[] = self::ERROR_CODE_MAP[$match];
        }

        return $errors;
    }

    /**
     * @return array{
     *      0: string,
     *      1: array<int, int|array<int, int>>,
     *      2: string,
     *      3: array<int, int|array<int, int>>,
     *      4: string,
     *      5: array<int, int|array<int, int>>
     * }
     */
    public function translate(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus,
        array $ignore = []
    ): array {
        if ($toUnicode === '') {
            $toUnicode = $source;
        }

        if ($toAsciiN === '') {
            $toAsciiN = $toUnicode;
        }

        if ($toAsciiT === '') {
            $toAsciiT = $toAsciiN;
        }

        $toUnicodeStatus = $this->resolveErrorCodes($toUnicodeStatus, [], $ignore);
        $toAsciiNStatus = $this->resolveErrorCodes($toAsciiNStatus, $toUnicodeStatus, $ignore);
        $toAsciiTStatus = $this->resolveErrorCodes($toAsciiTStatus, $toAsciiNStatus, $ignore);

        return [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ];
    }
}
