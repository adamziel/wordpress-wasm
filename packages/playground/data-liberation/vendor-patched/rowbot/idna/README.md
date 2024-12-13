# IDNA

[![License](https://img.shields.io/github/license/TRowbotham/idna?style=flat-square)](https://github.com/TRowbotham/URL-Parser/blob/master/LICENSE)
[![Build Status](https://img.shields.io/travis/com/TRowbotham/idna/master?style=flat-square)](https://travis-ci.com/github/TRowbotham/idna)
[![Code Coverage](https://img.shields.io/codecov/c/github/TRowbotham/idna/master?style=flat-square)](https://codecov.io/gh/TRowbotham/idna)
[![Version](https://img.shields.io/packagist/v/rowbot/idna?style=flat-square)](https://packagist.org/packages/rowbot/idna)
[![Downloads](https://img.shields.io/packagist/dt/rowbot/idna?style=flat-square)](https://packagist.org/packages/rowbot/idna)

A fully compliant implementation of [UTS#46](https://www.unicode.org/reports/tr46/), otherwise known
as Unicode IDNA Compatibility Processing. You can read more about the differences between IDNA2003,
IDNA2008, and UTS#46 in [Section 7. IDNA Comparison](https://www.unicode.org/reports/tr46/#IDNAComparison)
of the specification.

This library currently ships with Unicode 14.0.0 support and implements Version 14.0.0, Revision 27 of IDNA Compatibility
Processing. It has the ability to use Unicode 11.0.0 to Unicode 14.0.0. While this library likely supports versions of
Unicode less than 11.0.0, the format of the Unicode test files were changed beginning in 11.0.0 and as a result, versions
of Unicode less than 11.0.0 have not been tested.

-   [Requirements](#requirements)
-   [Installation](#installation)
-   [API](#api)
-   [Error Codes](#error-codes)
-   [The WTFs of Unicode Support in PHP](#the-wtfs-of-unicode-support-in-php)
-   [FAQs](#faqs)
-   [Internals](#internals)

## Requirements

-   PHP 7.1+
-   `rowbot/punycode`
-   `symfony/polyfill-intl-normalizer`

## Installation

```bash
composer require rowbot/idna
```

## API

### Idna::UNICODE_VERSION

The Unicode version of the data files used, as a string.

### Idna::toAscii(string $domain, array $options = []): IdnaResult

Converts a domain name to its ASCII form. Anytime an error is recorded while doing an ASCII
transformation, the transformation is considered to have failed and whatever domain name string is
returned is considered "garbage". What you do with that result is entirely up to you.

#### toAscii Parameters

-   $domain - A domain name to convert to ASCII.
-   $options - An array of options for customizing the behavior of the transformation. Possible
    options include:

    -   `"CheckBidi"` - Checks the domain name string for errors with bi-directional characters.
        Defaults to true.
    -   `"CheckHyphens"` - Checks the domain name string for the positioning of hypens. Defaults to
        true.
    -   `"CheckJoiners"` - Checks the domain name string for errors with joining code points. Defaults
        to true.
    -   `"UseSTD3ASCIIRules"` - Disallows the use of ASCII characters other than `[a-zA-Z0-9-]`.
        Defaults to true.
    -   `"Transitional_Processing"` - Whether transitional or non-transitional processing is used. When
        enabled, processing behaves more like IDNA2003 and when disabled behaves like IDNA2008. Defaults
        to false, which means that non-transitional processing is used by default.
    -   `"VerifyDnsLength"` - Validates the length of the domain name string and it's individual labels.
        Defaults to true.

    **Note**: All options are case-sensitive.

    ```php
    use Rowbot\Idna\Idna;

    $result = Idna::toAscii('x-.xn--nxa');

    // You must not use an ASCII domain that has errors.
    if ($result->hasErrors()) {
        throw new \Exception();
    }

    echo $result->getDomain(); // x-.xn--nxa
    ```

### Idna::toUnicode(string $domain, array $options = []): IdnaResult

Converts the domain name to its Unicode form. Unlike the toAscii transformation, toUnicode does not
have a failure concept. This means that you can always use the returned string. However, deciding
what to do with the returned domain name string when an error is recorded is entirely up to you.

-   $domain - A domain name to convert to UTF-8.
-   $options - An array of options for customizing the behavior of the transformation. Possible
    options include:

    -   `"CheckBidi"` - Checks the domain name string for errors with bi-directional characters.
        Defaults to true.
    -   `"CheckHyphens"` - Checks the domain name string for the positioning of hypens. Defaults to
        true.
    -   `"CheckJoiners"` - Checks the domain name string for errors with joining code points. Defaults
        to true.
    -   `"UseSTD3ASCIIRules"` - Disallows the use of ASCII characters other than `[a-zA-Z0-9-]`.
        Defaults to true.
    -   `"Transitional_Processing"` - Whether transitional or non-transitional processing is used. When
        enabled, processing behaves more like IDNA2003 and when disabled behaves like IDNA2008. Defaults
        to false, which means that non-transitional processing is used by default.

    **Note**: All options are case-sensitive.

    **Note**: `"VerifyDnsLength"` is not a valid option here.

    ```php
    use Rowbot\Idna\Idna;

    $result = Idna::toUnicode('xn---with-SUPER-MONKEYS-pc58ag80a8qai00g7n9n.com');
    echo $result->getDomain(); // 安室奈美恵-with-super-monkeys.com
    ```

### IdnaResult object

#### Members

##### IdnaResult::getDomain(): string

Returns the transformed domain name string.

##### IdnaResult::getErrors(): int

Returns a bitmask representing all errors that were recorded while processing the input domain name
string.

##### IdnaResult::hasError(int $error): bool

Returns whether or not a specific error was recorded.

##### IdnaResult::hasErrors(): bool

Returns whether or not an error was recorded while processing the input domain name string.

##### IdnaResult::isTransitionalDifferent(): bool

Returns `true` if the input domain name contains a code point that has a status of `"deviation"`.
This status indicates that the code points are handled differently in IDNA2003 than they are in
IDNA2008. At the time of writing, there are only 4 code points that have this status. They are
U+00DF, U+03C2, U+200C, and U+200D.

## Error Codes

-   `Idna::ERROR_EMPTY_LABEL`

    The domain name or one of it's labels are an empty string.

-   `Idna::ERROR_LABEL_TOO_LONG`

    One of the domain's labels exceeds 63 bytes.

-   `Idna::ERROR_DOMAIN_NAME_TOO_LONG`

    The length of the domain name exceeds 253 bytes.

-   `Idna::ERROR_LEADING_HYPHEN`

    One of the domain name's labels starts with a hyphen-minus character (-).

-   `Idna::ERROR_TRAILING_HYPHEN`

    One of the domain name's labels ends with a hyphen-minus character (-).

-   `Idna::ERROR_HYPHEN_3_4`

    One of the domain name's labels contains a hyphen-minus character in the 3rd and 4th position.

-   `Idna::ERROR_LEADING_COMBINING_MARK`

    One of the domain name's labels starts with a combining mark.

-   `Idna::ERROR_DISALLOWED`

    The domain name contains characters that are disallowed.

-   `Idna::ERROR_PUNYCODE`

    One of the domain name's labels starts with "xn--", but is not valid punycode.

-   `Idna::ERROR_LABEL_HAS_DOT`

    One of the domain name's labels contains a full stop character (.).

-   `Idna::ERROR_INVALID_ACE_LABEL`

    One of the domain name's labels is an invalid ACE label.

-   `Idna::ERROR_BIDI`

    The domain name does not meet the BiDi requirements for IDNA.

-   `Idna::ERROR_CONTEXTJ`

    One of the domain name's labels does not meet the CONTEXTJ requirements for IDNA.

## The WTFs of Unicode Support in PHP

In any given version of PHP, there can be a multitude of different versions of Unicode in use.
So... WTF?

-   What does this mean?

    This means that if I ask the same question, each of the extensions listed below can give me a
    different answer. This is compounded by the fact that the versions of Unicode used in the below
    extensions can also be different given the same version of PHP. For example, the `intl` extension
    being used by my installation of PHP 7.2 could be using Unicode version 11, but the `intl`
    extension in your web hosts installation of PHP 7.2 could be using Unicode version 6.

-   How does this happen?

    -   The `mbstring` extension uses its own version of Unicode.
    -   The `Onigurama` library, which is behind `mbstring`'s regular expression functions, uses its
        own version of Unicode.
    -   The `PCRE` extension, which is the primary extension for working with regular extensions in
        PHP, uses its own version of Unicode.
    -   The `intl` extension uses its own version of Unicode.
    -   Any other extensions that add their own versions of Unicode.
    -   Userland libraries use their own version of Unicode (including this library).

-   This library

    Being able to use `mbstring` or `intl` extensions would be helpful, but we cannot depend on them
    being installed or them having a consistent version of Unicode when they are installed.
    Additionally, extensions like `PCRE` could be compiled without Unicode support entirely, though we
    do rely on `PCRE`'s `u` modifier. For this reason we have to include our own Unicode data.

## FAQs

-   **I'm confused! Is this IDNA2003 or IDNA2008?**

    The answer to this is somewhat convoluted. TL;DR; It is neither.

    Here is what the spec says:

    > To satisfy user expectations for mapping, and provide maximal compatibility with IDNA2003, this
    > document specifies a mapping for use with IDNA2008. In addition, to transition more smoothly to
    > IDNA2008, this document provides a Unicode algorithm for a standardized processing that allows
    > conformant implementations to minimize the security and interoperability problems caused by the
    > differences between IDNA2003 and IDNA2008. This Unicode IDNA Compatibility Processing is
    > structured according to IDNA2003 principles, but extends those principles to Unicode 5.2 and
    > later. It also incorporates the repertoire extensions provided by IDNA2008.

    More information can be found in [Section 2. Unicode IDNA Compatibility Processing](https://www.unicode.org/reports/tr46/#Compatibility_Processing)
    and [Section 7. IDNA Comparison](https://www.unicode.org/reports/tr46/#IDNAComparison).

-   **What are the recommended options?**

    The default options are the recommended options, which are also the strictest.

    ```php
    // Default options.
    [
      'CheckHyphens'            => true,
      'CheckBidi'               => true,
      'CheckJoiners'            => true,
      'UseSTD3ASCIIRules'       => true,
      'Transitional_Processing' => false,
      'VerifyDnsLength'         => true, // Only for Idna::toAscii()
    ];
    ```

-   **Do I have to provide all the options?**

    No. You only need to specifiy the options that you wish to change. Any option you specify will
    overwrite the default options.

    ```php
    use Rowbot\Idna\Idna;

    $result = Idna::toAscii('x-.xn--nxa', ['CheckHyphens' => true]);
    $result->hasErrors(); // true
    $result->hasError(Idna::ERROR_TRAILING_HYPHEN); // true

    $result = Idna::toAscii('x-.xn--nxa', ['CheckHyphens' => false]);
    $result->hasErrors(); // false
    $result->hasError(Idna::ERROR_TRAILING_HYPHEN); // false
    ```

-   **What is the difference between `Transitional` and `Non-transitional` processing?**

    `Transitional` processing is designed to mimic IDNA2003. It is highly recommended to use
    `Non-transitional` processing, which tries to mimic IDNA2008. You can always check if a domain
    name would be different between the two processing modes by checking
    `IdnaResult::isTransitionalDifferent()`.

-   **Wouldn't it be neat if you also tested against the `idn_to_ascii()` and `idn_to_utf8()`
    functions from the `intl` extension?**

    Yes. Yes, it would be neat if we could do an additional check for parity with the ICU
    implementation, however, for the reasons outlined above in
    [The WTFs of Unicode Support in PHP](#the-wtfs-of-unicode-support-in-php), testing against these
    functions would be unreliable at best.

-   **Why does the `intl` extension show weird characters that look like diamonds with question marks
    inside in invalid domains, but your implementation doesn't?**

    ```php
    $input = '憡?Ⴔ.XN--1UG73GL146A';

    idn_to_utf8($input, 0, IDNA_INTL_VARIANT_UTS46, $info);
    echo $info['result']; // 憡��.xn--1ug73gl146a�
    echo ($info['errors'] & IDNA_ERROR_DISALLOWED) !== 0; // true

    $result = \Rowbot\Idna\Idna::toUnicode($input);
    echo $result->getDomain(); // 憡?Ⴔ.xn--1ug73gl146a
    echo $result->hasError(\Rowbot\Idna\Idna::ERROR_DISALLOWED); // true
    ```

    From [Section 4. Processing](https://www.unicode.org/reports/tr46/#Processing):

    > Implementations may make further modifications to the resulting Unicode string when showing it to
    > the user. For example, it is recommended that disallowed characters be replaced by a U+FFFD to
    > make them visible to the user. Similarly, labels that fail processing during steps 4 or 5 may be
    > marked by the insertion of a U+FFFD or other visual device.

    This implementation currently does not make these recommended modifications.

## Internals

### Building

Unicode data files are fetched from https://www.unicode.org/Public. Currently, Unicode version
11.0.0-14.0.0 are supported. To change the version of Unicode that the library is built with, you
must first change the value of the `\Rowbot\Idna::UNICODE_VERSION` constant, like so:

```diff
class Idna
{
-     public const UNICODE_VERSION = '13.0.0';
+     public const UNICODE_VERSION = '14.0.0';
```

Then to generate the necessary data files, you execute the following command:

```bash
php bin/generateDataFiles.php
```

If no assertions or exceptions have occured, then you have successfully changed the Unicode version.
You should now execute the tests to make sure everything is good to go. The tests will automatically
fetch the version appropriate tests as the test files are not generated by the above command.

```bash
vendor/bin/phpunit
```
