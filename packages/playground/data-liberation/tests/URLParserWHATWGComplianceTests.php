<?php

use Rowbot\URL\URL;
use PHPUnit\Framework\TestCase;

function load_test_examples() {
    $json = file_get_contents(__DIR__ . '/whatwg_url_test_data.json');
    /**
     * Removes UTF-16 sequences from a JSON string since PHP doesn't know how to decode
     * them and returns null from json_decode.
     *
     * @TODO: Isolate the problematic inputs and figure out what to do with them.
     *        Re-encode all the examples as XML? Or PHP serialize() format?
     */
    // $json = preg_replace_callback(
    //     '~(?<!\\\)\\\u[a-f0-9]{4}~iu',
    //     function($found){
    //     if(json_decode('"'.$found[0].'"')){
    //         return $found[0];
    //     }
    //     return "";  //or "?"
    //     },
    //     $json
    // );
    return json_decode($json, true);
}

class URLParserWHATWGComplianceTests extends TestCase
{

    /**
     * Test url_parser function.
     *
     * @dataProvider data_valid_urls
     * @return void
     */
    public function test_parse_url($example)
    {
        $this->assertTrue(URL::canParse($example['input'], $example['base']));

        $parsed = new URL($example['input'], $example['base']);
        $this->assertEquals($example['protocol'], $parsed->protocol);
        $this->assertEquals($example['username'], $parsed->username);
        $this->assertEquals($example['password'], $parsed->password);
        $this->assertEquals($example['host'], $parsed->host);
        $this->assertEquals($example['port'], $parsed->port);
        $this->assertEquals($example['hostname'], $parsed->hostname);
        $this->assertEquals($example['pathname'], $parsed->pathname);
        $this->assertEquals($example['search'], $parsed->search);
        $this->assertEquals($example['hash'], $parsed->hash);
        if (isset($example['origin'])) {
            $this->assertEquals($example['origin'], $parsed->origin);
        }
    }

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	static public function data_valid_urls() {
        static $test_examples = null;
        if(null === $test_examples) {
            $test_examples = load_test_examples();
        }

        $valid_urls = array();
        foreach($test_examples as $example) {
            if(is_string($example)) {
                continue;
            }
            if (!isset($example['failure'])) {
                $valid_urls[] = [$example];
            }
        }
        return $valid_urls;

        // @TODO: Figure out why this test case fails. I had to remove it from urltestdata.json because
        //        the UTF-16 sequences made the json_code return null. The error was:
        //
        //        > Single unpaired UTF-16 surrogate in unicode escape
        //
        // $valid_urls[] = array(
        //     array(
        //         "input" => "http://example.com/".encodeUtf16Hex('d800')."𐟾".encodeUtf16Hex('dfff')."﷐﷏﷯ﷰ￾￿?".encodeUtf16Hex('d800')."𐟾".encodeUtf16Hex('dfff')."﷐﷏﷯ﷰ￾￿",
        //         "base" => null,
        //         "href" => "http://example.com/%EF%BF%BD%F0%90%9F%BE%EF%BF%BD%EF%B7%90%EF%B7%8F%EF%B7%AF%EF%B7%B0%EF%BF%BE%EF%BF%BF?%EF%BF%BD%F0%90%9F%BE%EF%BF%BD%EF%B7%90%EF%B7%8F%EF%B7%AF%EF%B7%B0%EF%BF%BE%EF%BF%BF",
        //         "origin" => "http://example.com",
        //         "protocol" => "http:",
        //         "username" => "",
        //         "password" => "",
        //         "host" => "example.com",
        //         "hostname" => "example.com",
        //         "port" => "",
        //         "pathname" => "/%EF%BF%BD%F0%90%9F%BE%EF%BF%BD%EF%B7%90%EF%B7%8F%EF%B7%AF%EF%B7%B0%EF%BF%BE%EF%BF%BF",
        //         "search" => "?%EF%BF%BD%F0%90%9F%BE%EF%BF%BD%EF%B7%90%EF%B7%8F%EF%B7%AF%EF%B7%B0%EF%BF%BE%EF%BF%BF",
        //         "hash" => ""
        //     ),
        // ),
	}

    /**
     * Test url_parser function.
     *
     * @dataProvider data_invalid_urls
     * @return void
     */
    public function test_parse_invalid_url($example)
    {
        $this->assertFalse(
            URL::canParse($example['input'], $example['base']),
            "Should have rejected invalid URL {$example['input']} with base {$example['base']}"
        );
    }

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	static public function data_invalid_urls() {
        static $test_examples = null;
        if(null === $test_examples) {
            $test_examples = load_test_examples();
        }

        $urls = array();
        foreach($test_examples as $example) {
            if(is_string($example)) {
                continue;
            }
            if (array_key_exists('failure', $example) && $example['failure'] === true) {
                $urls[] = [$example];
            }
        }
        return $urls;
	}

}
