<?php

use PHPUnit\Framework\TestCase;
use WordPress\Filesystem\WP_In_Memory_Filesystem;

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

}