# Punycode

[![Software License](https://img.shields.io/github/license/TRowbotham/punycode?style=flat-square)](https://github.com/TRowbotham/punycode/blob/master/LICENSE)
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/TRowbotham/punycode/Tests?style=flat-square)](https://travis-ci.com/github/TRowbotham/punycode)
[![Code Coverage](https://img.shields.io/codecov/c/github/TRowbotham/punycode/master?style=flat-square)](https://codecov.io/gh/TRowbotham/punycode)
[![PHP Version](https://img.shields.io/packagist/v/rowbot/punycode?style=flat-square)](https://packagist.org/packages/rowbot/punycode)
[![Total Downloads](https://img.shields.io/packagist/dt/rowbot/punycode?style=flat-square)](https://packagist.org/packages/rowbot/punycode)

An implementation of RFC 3492 Punycode in PHP, based on the sample implementation in [Appendix C](https://tools.ietf.org/html/rfc3492#appendix-C).
This is NOT a substitue for `idn_to_ascii` and `idn_to_utf8`.

## Requirements

-   PHP 7.1+

## Installation

```bash
composer require rowbot/punycode
```

## API

### Punycode::decode(string $input, int $outLength = null, array &$caseFlags = [])

The `Punycode::decode()` method takes an ASCII encoded string and decodes it to a UTF-8 encoded
string. Optionally, the second parameter can be specified to place a limit on the size of the
returned string.

#### Parameters

-   `$input` - An ASCII encoded punycode string to convert to a UTF-8 encoded string.
-   `$outLength` - A positive integer representing the maximum length, in code points, of the resulting
    output string. Defaults to 2,147,483,647.
-   `$caseFlags` - An array, which will have the case flag of each character inserted into it.

#### Throws

-   `\Rowbot\Punycode\Exception\OutputSizeExceededException` - If the size of the output string
    exceeds the maximum size specified.
-   `\Rowbot\Punycode\Exception\OverflowException` - If integer overflow occurs.
-   `\Rowbot\Punycode\Exception\InvalidInputException` - If input contains non-ASCII bytes or mapping
    a code point to a digit fails.

```php
use Rowbot\Punycode\Punycode;

try {
    echo Punycode::decode('Hello-Another-Way--fc4qua05auwb3674vfr0b'); // Hello-Another-Way-それぞれの場所
} catch (\Rowbot\Punycode\Exception\PunycodeException $e) {
    echo 'An error occured!';
}
```

### Punycode::encode(string $input, int $outLength = null, array $caseFlags = [])

The `Punycode::encode()` method takes a UTF-8 encoded string and converts it into an ASCII encoded
punycode string. Optionally, the second parameter can be specified to place a limit on the size of
the returned string.

#### Parameters

-   `$input` - A UTF-8 encoded string to convert to punycode.
-   `$outLength` - A positive integer representing the maximum length, in code points, of the resulting
    output string. Defaults to 2,147,483,647.
-   `$caseFlags` - An array of bools where true indicates that the character should be uppercase and
    false indicates that it should be lowercase. This only affects ASCII characters `[a-zA-Z]`.

#### Throws

-   `\Rowbot\Punycode\Exception\OutputSizeExceededException` - If the size of the output string
    exceeds the maximum size specified.
-   `\Rowbot\Punycode\Exception\OverflowException` - If integer overflow occurs.

```php
use Rowbot\Punycode\Punycode;

try {
    echo Punycode::encode('Hello-Another-Way-それぞれの場所'); // Hello-Another-Way--fc4qua05auwb3674vfr0b
} catch (\Rowbot\Punycode\Exception\PunycodeException $e) {
    echo 'An error occured!';
}
```
