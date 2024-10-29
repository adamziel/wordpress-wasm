<?php

use PHPUnit\Framework\TestCase;

class WPWXRURLRewriterTests extends TestCase {
    
    /**
     * @dataProvider get_fixture_paths
     */
    public function test_process($fixture_path, $expected_outcome_path) {
        $chain = new WP_Stream_Chain(
            [
                'file' => new WP_File_Byte_Stream($fixture_path, 100),
                'wxr' => WP_WXR_URL_Rewrite_Processor::create_stream_processor(
                    'https://playground.internal/path',
                    'https://playground.wordpress.net/new-path'
                ),
            ]
        );
        $actual_output = $chain->run_to_completion();
        $expected_output = file_get_contents($expected_outcome_path);
        $this->assertSame($expected_output, $actual_output);
    }

    public function get_fixture_paths() {
        return [
            [__DIR__ . '/fixtures/wxr-simple.xml', __DIR__ . '/fixtures/wxr-simple-expected.xml'],
        ];
    }

}
