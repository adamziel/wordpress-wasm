<?php

use PHPUnit\Framework\TestCase;
use WordPress\Filesystem\WP_In_Memory_Filesystem;
use WordPress\AsyncHttp\ResponseWriter\BufferingResponseWriter;

class WPGitServerTests extends TestCase {

    private $server;
    private $repository;
    private $main_branch_oid;
    private $dev_branch_oid;

    protected function setUp(): void {
        $this->repository = new WP_Git_Repository(
            new WP_In_Memory_Filesystem()
        );
        $this->server = new WP_Git_Server($this->repository);
        $this->repository->set_ref_head('HEAD', 'ref: refs/heads/main');
        $this->main_branch_oid = $this->repository->commit([
            'updates' => [
                'README.md' => 'Hello, world!',
            ],
        ]);

        $this->repository->set_ref_head('refs/heads/main', $this->main_branch_oid);
        $this->repository->set_ref_head('refs/heads/twin', $this->main_branch_oid);
        $this->repository->set_ref_head('refs/heads/main-backup', $this->main_branch_oid);
        $this->repository->set_ref_head('refs/heads/dev', $this->main_branch_oid);
        $this->repository->set_ref_head('HEAD', 'ref: refs/heads/dev');

        $this->dev_branch_oid = $this->repository->commit([
            'updates' => [
                'DEV.md' => 'Another file!',
            ],
        ]);
    }

    /**
     * @dataProvider provideRequestData
     */
    public function test_parse_message($request, $expected) {
        $result = $this->server->parse_message($request);
        $this->assertEquals($expected, $result);
    }

    public function provideRequestData() {
        return [
            'basic ls-refs request' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=ls-refs\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'ls-refs',
                    ],
                    'arguments' => [],
                ]
            ],
            'request with multiple capabilities' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=ls-refs\n",
                    "agent=git/2.37.3\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'ls-refs',
                        'agent' => 'git/2.37.3'
                    ],
                    'arguments' => [],
                ]
            ],
            'request with multiple arguments' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=ls-refs\n",
                    "0001",
                    "peel\n",
                    "ref-prefix HEAD\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'ls-refs',
                    ],
                    'arguments' => [
                        'peel' => [true],
                        'ref-prefix' => ['HEAD']
                    ],
                ]
            ],
            'basic want request' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "agent=git/2.37.3\n",
                    "object-format=sha1\n",
                    "0000",
                    "want e0d02a851d0c461a7c725dc69eb2d53f57f666a6\n",
                    "done\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'fetch',
                        'agent' => 'git/2.37.3',
                        'object-format' => 'sha1'
                    ],
                    'arguments' => [
                        'want' => ['e0d02a851d0c461a7c725dc69eb2d53f57f666a6'],
                        'done' => [true]
                    ]
                ]
            ],
            'want with have and filter' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "agent=git/2.37.3\n",
                    "object-format=sha1\n",
                    "0000",
                    "want e0d02a851d0c461a7c725dc69eb2d53f57f666a6\n",
                    "have f5b97d7b9af357c81b5df5773329d50f764c2992\n",
                    "filter blob:none\n",
                    "done\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'fetch',
                        'agent' => 'git/2.37.3',
                        'object-format' => 'sha1'
                    ],
                    'arguments' => [
                        'want' => ['e0d02a851d0c461a7c725dc69eb2d53f57f666a6'],
                        'have' => ['f5b97d7b9af357c81b5df5773329d50f764c2992'],
                        'filter' => ['blob:none'],
                        'done' => [true]
                    ]
                ]
            ],
            'want with deepen and blob size limit' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "agent=git/2.37.3\n",
                    "object-format=sha1\n",
                    "0000",
                    "want e0d02a851d0c461a7c725dc69eb2d53f57f666a6\n",
                    "filter blob:limit=1000\n",
                    "deepen 10\n",
                    "done\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'fetch',
                        'agent' => 'git/2.37.3',
                        'object-format' => 'sha1'
                    ],
                    'arguments' => [
                        'want' => ['e0d02a851d0c461a7c725dc69eb2d53f57f666a6'],
                        'filter' => ['blob:limit=1000'],
                        'deepen' => ['10'],
                        'done' => [true]
                    ]
                ]
            ],
            'multiple want and have' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "0000",
                    "want e0d02a851d0c461a7c725dc69eb2d53f57f666a6\n",
                    "want f10e2821bbbea527ea02200352313bc059445190\n",
                    "have f5b97d7b9af357c81b5df5773329d50f764c2992\n",
                    "have 0e747aaa0f03a7b7bb9a964f47fe7c508be7b086\n",
                    "done\n",
                    "0000",
                ]),
                [
                    'capabilities' => [
                        'command' => 'fetch',
                    ],
                    'arguments' => [
                        'want' => [
                            'e0d02a851d0c461a7c725dc69eb2d53f57f666a6',
                            'f10e2821bbbea527ea02200352313bc059445190'
                        ],
                        'have' => [
                            'f5b97d7b9af357c81b5df5773329d50f764c2992',
                            '0e747aaa0f03a7b7bb9a964f47fe7c508be7b086'
                        ],
                        'done' => [true],
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider provideRefRequests
     */
    public function test_handle_ls_refs_returns_matching_refs($request, $expected_response) {
        // Replace placeholders with actual values in the test as $this->main_branch_oid and
        // $this->dev_branch_oid are not available in the data provider.
        $expected_response = str_replace(
            array('{main_branch_oid}', '{dev_branch_oid}'),
            array($this->main_branch_oid, $this->dev_branch_oid),
            $expected_response
        );
        $response = $this->server->handle_ls_refs_request($request);
        $this->assertEquals($expected_response, $response);
    }

    public function provideRefRequests() {
        return [
            'all refs under heads' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=ls-refs\n",
                    "0000",
                ]),
                <<<RESPONSE
003d{main_branch_oid} refs/heads/main
003d{main_branch_oid} refs/heads/twin
0044{main_branch_oid} refs/heads/main-backup
003c{dev_branch_oid} refs/heads/dev
0032{dev_branch_oid} HEAD
0000
RESPONSE
            ],
            'specific branch' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=ls-refs\n",
                    "0001",
                    "peel\n",
                    "ref-prefix refs/heads/main\n",
                    "0000",
                ]),
                <<<RESPONSE
003d{main_branch_oid} refs/heads/main
0044{main_branch_oid} refs/heads/main-backup
0000
RESPONSE
            ],
            'HEAD ref' => [
                WP_Git_Pack_Processor::encode_packet_lines([
                    "command=ls-refs\n",
                    "0001",
                    "peel\n",
                    "ref-prefix HEAD\n",
                    "0000",
                ]),
                <<<RESPONSE
0032{dev_branch_oid} HEAD
0000
RESPONSE
            ],
        ];
    }

    public function test_handle_fetch_request_returns_packfile() {
        // Create a more complex repository structure for testing
        $readme_oid = $this->repository->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_BLOB,
            "# Hello World"
        );
        $large_file_oid = $this->repository->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_BLOB,
            str_repeat('x', 2000) // 2KB file
        );
        
        $tree_oid = $this->repository->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_TREE,
            WP_Git_Pack_Processor::encode_tree_bytes([
                [
                    'mode' => WP_Git_Pack_Processor::FILE_MODE_REGULAR_NON_EXECUTABLE,
                    'name' => 'README.md',
                    'sha1' => $readme_oid
                ],
                [
                    'mode' => WP_Git_Pack_Processor::FILE_MODE_REGULAR_NON_EXECUTABLE,
                    'name' => 'large.txt',
                    'sha1' => $large_file_oid
                ]
            ])
        );

        $commit_oid = $this->repository->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT,
            "tree $tree_oid\nparent 0000000000000000000000000000000000000000\nauthor Test <test@example.com> 1234567890 +0000\ncommitter Test <test@example.com> 1234567890 +0000\n\nInitial commit\n"
        );

        $test_cases = [
            'basic fetch' => [
                'request' => WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "0000",
                    "want $commit_oid\n",
                    "done\n",
                    "0000",
                ]),
                'expected_oids' => [
                    $commit_oid,
                    $tree_oid,
                    $readme_oid,
                    $large_file_oid,
                ],
            ],
            'fetch with blob:none filter' => [
                'request' => WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "0000",
                    "want $commit_oid\n",
                    "filter blob:none\n",
                    "done\n",
                    "0000",
                ]),
                'expected_oids' => [
                    $commit_oid,
                    $tree_oid,
                ],
            ],
            'fetch with blob size limit' => [
                'request' => WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "0000",
                    "want $commit_oid\n",
                    "filter blob:limit=1000\n",
                    "done\n",
                    "0000",
                ]),
                'expected_oids' => [
                    $commit_oid,
                    $tree_oid,
                    $readme_oid,
                ],
            ],
            'fetch with multiple wants' => [
                'request' => WP_Git_Pack_Processor::encode_packet_lines([
                    "command=fetch\n",
                    "0000",
                    "want $commit_oid\n",
                    "want $tree_oid\n",
                    "done\n",
                    "0000",
                ]),
                // same objects, just different entry point
                'expected_oids' => [
                    $commit_oid,
                    $tree_oid,
                    $readme_oid,
                    $large_file_oid,
                ],
            ]
        ];

        foreach ($test_cases as $name => $test) {
            /** @var BufferingResponseWriter */
            $response = $this->getMockBuilder(BufferingResponseWriter::class)
                ->onlyMethods(['end'])
                ->getMock();
            $this->server->handle_fetch_request($test['request'], $response);
            
            // Verify response format
            $response = $response->get_buffered_body();
            $expected_response_start = WP_Git_Pack_Processor::encode_packet_lines([
                "acknowledgments\n",
                "NACK\n",
                "ready\n",
                "0001",
                "packfile\n",
            ]);
            $actual_response_start = substr($response, 0, strlen($expected_response_start));
            $this->assertEquals(
                $expected_response_start,
                $actual_response_start,
                "$name: Response should start with packfile header"
            );

            $rest_of_response = substr($response, strlen($expected_response_start));
            $pack_data = WP_Git_Client::accumulate_pack_data_from_multiplexed_chunks(
                $rest_of_response
            );
            $pack = WP_Git_Pack_Processor::parse_pack_data($pack_data);

            $this->assertCount(
                count($test['expected_oids']),
                $pack['objects'],
                "$name: Pack should contain expected number of objects"
            );
            foreach($pack['objects'] as $object) {
                $this->assertContains($object['oid'], $test['expected_oids']);
            }
        }
    }

    // public function test_handle_fetch_request_validates_filter() {
    //     $this->expectException(Exception::class);
    //     $this->expectExceptionMessage('Invalid filter: invalid:filter');

    //     $request = WP_Git_Pack_Processor::encode_packet_lines([
    //         "command=fetch\n",
    //         "0000",
    //         "want " . $this->main_branch_oid . "\n",
    //         "filter invalid:filter\n",
    //         "done\n",
    //         "0000",
    //     ]);

    //     $this->server->handle_fetch_request($request);
    // }

    // public function test_handle_fetch_request_requires_want() {
    //     $request = WP_Git_Pack_Processor::encode_packet_lines([
    //         "command=fetch\n",
    //         "0000",
    //         "done\n",
    //         "0000",
    //     ]);

    //     $this->assertFalse(
    //         $this->server->handle_fetch_request($request),
    //         "Fetch request without want should return false"
    //     );
    // }

}