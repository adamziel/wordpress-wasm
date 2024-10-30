<?php

require __DIR__ . '/cors-proxy-config.php';

function assert_equal($expected, $actual, $message='') {
	if ($expected !== $actual) {
        $message = $message ?: "Test failed.";
		echo "$message.\nExpected: $expected\nActual:   $actual\n";
        die();
	}
}

function assert_throws($expected_message, $callback) {
    try {
        $callback();
    } catch (Exception $e) {
        if ($e->getMessage() !== $expected_message) {
            echo "Test failed.\nExpected: $expected_message\nActual:   {$e->getMessage()}\n";
            die();
        }
        return;
    }
    echo "Test failed.\nExpected: $expected_message\nActual:   No exception was thrown\n";
    die();
}

assert_equal(
    '2607:B4C0:0000:0000:0000:0000:0000:0000',
    playground_ip_to_a_64_subnet(
        '2607:B4C0:0000:0000:0000:0000:0000:0001'
    ),
    'IPv6 was not correctly transformed into a subnet'
);

assert_equal(
    '2607:B4C0:AAAA:BBBB:0000:0000:0000:0000',
    playground_ip_to_a_64_subnet(
        '2607:B4C0:AAAA:BBBB:CCCC:DDDD:EEEE:FFFF'
    ),
    'IPv6 was not correctly transformed into a subnet'
);

assert_equal(
    '::ffff:127.0.0.1', 
    playground_ip_to_a_64_subnet('127.0.0.1', 64),
    'A part of the IPv4 range was lost'
);

assert_throws(
    'Block size must be a multiple of 8.',
    function () {
        playground_get_ipv6_block(
            '2607:B4C0:AAAA:BBBB:CCCC:DDDD:EEEE:FFFF',
            8 - 1
        );
    }
);

assert_throws(
    'Block size must be less than or equal to 128.',
    function () {
        playground_get_ipv6_block(
            '2607:B4C0:AAAA:BBBB:CCCC:DDDD:EEEE:FFFF',
            128 + 8
        );
    }
);

echo 'All tests passed';
