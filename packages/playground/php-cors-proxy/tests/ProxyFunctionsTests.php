<?php

use PHPUnit\Framework\TestCase;

class ProxyFunctionsTests extends TestCase
{

    /**
     * 
     * @dataProvider providerIps
     */
    public function testIsPrivateIp($ip, $is_private)
    {
        $this->assertEquals($is_private, is_private_ip($ip), "IP $ip was not detected as " . ($is_private ? 'private' : 'public'));
    }

    static public function providerIps()
    {
        return [
            ['127.0.0.1', true],      // Loopback address
            ['192.168.1.1', true],    // Private network
            ['10.0.0.1', true],       // Private network
            ['172.16.0.1', true],     // Private network
            ['172.31.255.255', true], // Private network end
            ['8.8.8.8', false],       // Public IP address (Google DNS)
            ['54.239.28.85', false],  // Public IP address
            ['192.88.99.1', true], 
            ['::1', true],           // Loopback IPv6
            ['fd00::', true],        // Unique Local Address IPv6
            ['fe80::', true],        // Link-local IPv6 address
            ['2001:db8::', true],    
            ['64:ff9b::0:0', true],    
            ['2001:4860:4860::8888', false], // Google Public IPv6 DNS
            ['204.79.197.200', false] // Public IP address (Microsoft)
        ];
    }

    /**
     * 
     * @dataProvider providerRewriteRelativeRedirect
     */
    public function testRewriteRelativeRedirect($request_url, $redirect_location, $proxy_absolute_url, $expected)
    {
        $this->assertEquals($expected, rewrite_relative_redirect($request_url, $redirect_location, $proxy_absolute_url));
    }

    static public function providerRewriteRelativeRedirect() {
        return [
            'Relative redirect to a trailing slash path' => [
                'https://w.org/hosting',
                '/hosting/',
                'https://cors.playground.wordpress.net/proxy.php',
                'https://cors.playground.wordpress.net/proxy.php?https://w.org/hosting/'
            ],
            'Relative redirect when the proxy URL has a trailing slash itself' => [
                'https://w.org/hosting',
                '/hosting/',
                'https://cors.playground.wordpress.net/proxy.php/',
                'https://cors.playground.wordpress.net/proxy.php/https://w.org/hosting/'
            ],
            'Relative redirect with query params involved' => [
                'https://w.org/hosting',
                '/hosting/?utm_source=wporg',
                'https://cors.playground.wordpress.net/proxy.php',
                'https://cors.playground.wordpress.net/proxy.php?https://w.org/hosting/?utm_source=wporg'
            ],
            'Absolute redirect with query params involved' => [
                'https://w.org/hosting',
                'https://w.net/hosting/?utm_source=wporg',
                'https://cors.playground.wordpress.net/proxy.php',
                'https://cors.playground.wordpress.net/proxy.php?https://w.net/hosting/?utm_source=wporg'
            ],
        ];
    }
    
    /**
     * 
     * @dataProvider providerGetTargetUrl
     */
    public function testGetTargetUrl($server_data, $expected_target_url)
    {
        $this->assertEquals($expected_target_url, get_target_url($server_data));
    }

    static public function providerGetTargetUrl() {
        return [
            'Request with server-provided PATH_INFO' => [
                [
                    'PATH_INFO' => '/http://example.com',
                ],
                'http://example.com'
            ],
            'Request with server-provided single-slash PATH_INFO' => [
                [
                    'PATH_INFO' => '/',
                ],
                false,
            ],
            'Request with server-provided empty PATH_INFO' => [
                [
                    'PATH_INFO' => '',
                ],
                false,
            ],
            'Request with server-provided PATH_INFO and QUERY_STRING' => [
                [
                    'PATH_INFO' => '/http://example.com/from-path-info',
                    'QUERY_STRING' => 'http://example.com/from-query-string',
                ],
                'http://example.com/from-path-info'
            ],
            'Request with server-provided slash PATH_INFO and QUERY_STRING' => [
                [
                    'PATH_INFO' => '/',
                    'QUERY_STRING' => 'http://example.com/from-query-string',
                ],
                'http://example.com/from-query-string'
            ],
            'Request with just query params' => [
                [
                    'QUERY_STRING' => 'http://example.com/from-query-string',
                ],
                'http://example.com/from-query-string'
            ],
            'Request with neither PATH_INFO nor QUERY_STRING' => [
                [],
                false
            ],
        ];
    }
    public function testGetCurrentScriptUri()
    {
        $this->assertEquals('http://localhost/cors-proxy/', get_current_script_uri('http://example.com', 'http://localhost/cors-proxy/http://example.com'));
    }

    public function testUrlValidateAndResolve()
    {
        $this->expectException(CorsProxyException::class);
        url_validate_and_resolve('ftp://example.com');
    }

    public function testUrlValidateAndResolveWithTargetSelf()
    {
        $this->expectException(CorsProxyException::class);
        $_SERVER['HTTP_HOST'] = 'cors.playground.wordpress.net';
        url_validate_and_resolve(
            'http://cors.playground.wordpress.net/cors-proxy.php?http://cors.playground.wordpress.net'
        );
    }

    public function testFilterHeadersStrings()
    {
        $original_headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Cookie' => 'test=1',
            'Host' => 'example.com',
        ];

        $strictly_disallowed_headers = [
            'Cookie',
            'Host',
        ];

        $headers_requiring_opt_in = [
            'Authorization',
        ];

        $this->assertEquals(
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            filter_headers_by_name(
                $original_headers,
                $strictly_disallowed_headers,
                $headers_requiring_opt_in,
            )
        );
    }

    public function testFilterHeaderStringsWithAdditionalAllowedHeaders()
    {
        $original_headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Cookie' => 'test=1',
            'Host' => 'example.com',
            'Authorization' => 'Bearer 1234567890',
            'X-Authorization' => 'Bearer 1234567890',
            'X-Cors-Proxy-Allowed-Request-Headers' => 'Authorization',
        ];

        $strictly_disallowed_headers = [
            'Cookie',
            'Host',
        ];

        $headers_requiring_opt_in = [
            'Authorization',
        ];

        $this->assertEquals(
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer 1234567890',
                'X-Authorization' => 'Bearer 1234567890',
                'X-Cors-Proxy-Allowed-Request-Headers' => 'Authorization',
            ],
            filter_headers_by_name(
                $original_headers,
                $strictly_disallowed_headers,
                $headers_requiring_opt_in,
            )
        );
    }
}
