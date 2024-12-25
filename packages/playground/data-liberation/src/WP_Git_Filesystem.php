<?php

class WP_Git_Index {

    const OBJECT_TYPE_COMMIT = 1;
    const OBJECT_TYPE_TREE = 2;
    const OBJECT_TYPE_BLOB = 3;
    const OBJECT_TYPE_TAG = 4;
    const OBJECT_TYPE_RESERVED = 5;
    const OBJECT_TYPE_OFS_DELTA = 6;
    const OBJECT_TYPE_REF_DELTA = 7;

    const OBJECT_NAMES = [
        self::OBJECT_TYPE_COMMIT => 'commit',
        self::OBJECT_TYPE_TREE => 'tree',
        self::OBJECT_TYPE_BLOB => 'blob',
        self::OBJECT_TYPE_TAG => 'tag',
        self::OBJECT_TYPE_RESERVED => 'reserved',
        self::OBJECT_TYPE_OFS_DELTA => 'ofs_delta',
        self::OBJECT_TYPE_REF_DELTA => 'ref_delta',
    ];

    const FILE_MODE_DIRECTORY = '040000';
    const FILE_MODE_REGULAR_NON_EXECUTABLE = '100644';
    const FILE_MODE_REGULAR_EXECUTABLE = '100755';
    const FILE_MODE_SYMBOLIC_LINK = '120000';
    const FILE_MODE_COMMIT = '160000';

    const FILE_MODE_NAMES = [
        self::FILE_MODE_DIRECTORY => 'directory',
        self::FILE_MODE_REGULAR_NON_EXECUTABLE => 'regular_non_executable',
        self::FILE_MODE_REGULAR_EXECUTABLE => 'regular_executable',
        self::FILE_MODE_SYMBOLIC_LINK => 'symbolic_link',
        self::FILE_MODE_COMMIT => 'commit',
    ];

    private $repoUrl;

    public function __construct($repoUrl) {
        $this->repoUrl = rtrim($repoUrl, '/');
    }
    
    public function fetchFiles($directory = '') {
        $headRef = $this->fetchRefHash('HEAD');
        if (!$headRef) {
            return false;
        }

        $index = $this->list_objects($headRef);
        if(false === $index) {
            return false;
        }

        $commit_tree_sha = $index['objects'][$index['by_oid'][$headRef]]['tree'];
        $commit_tree = $index['objects'][$index['by_oid'][$commit_tree_sha]]['content'];
        $subdir = $index['objects'][$index['by_oid'][$commit_tree['wp-content']['sha1']]]['content'];
        $subdir = $index['objects'][$index['by_oid'][$subdir['html-pages']['sha1']]]['content'];
        $subdir = $index['objects'][$index['by_oid'][$subdir['0_wordpress-playground']['sha1']]]['content'];

        $refs = [];
        foreach($subdir as $file) {
            $refs[] = $file['sha1'];
        }

        $blobs = $this->fetch_blobs($refs);
        var_dump($blobs);
        var_dump($refs);
        die();

        return $index;
    }

    private function fetchRefHash($ref_name) {
        // Fetch HEAD ref
        $url = $this->repoUrl . '/git-upload-pack';
        $response = $this->http_request(
            $url,
            $this->encode_packet_line("command=ls-refs\n") .
            $this->encode_packet_line("agent=git/2.37.3\n") .
            $this->encode_packet_line("object-format=sha1\n") .
            "0001" .
            $this->encode_packet_line("peel\n") .
            $this->encode_packet_line("ref-prefix $ref_name\n") .
            "0000",
            [
                'Accept: application/x-git-upload-pack-advertisement',
                'Content-Type: application/x-git-upload-pack-request', 
                'Git-Protocol: version=2'
            ]
        );

        if (!$response) {
            return false;
        }

        $ref_hash = null;
        foreach ($this->parse_git_protocol_v2_packets($response) as $frame) {
            if (strpos($frame, $ref_name) !== false) {
                $ref_hash = trim(explode(' ', $frame)[0]);
                break;
            }
        }

        if (!$ref_hash) {
            return false;
        }

        return $ref_hash;
    }

    private function parse_git_protocol_v2_packets($bytes) {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $length = hexdec(substr($bytes, $offset, 4));
            $offset += 4;
            $frame = substr($bytes, $offset, $length);
            yield $frame;
            $offset += $length;
        }
    }

    private function list_objects($ref_hash) {
        $body = 
            $this->encode_packet_line("want {$ref_hash} multi_ack_detailed no-done side-band-64k thin-pack ofs-delta agent=git/2.37.3 filter\n") .
            $this->encode_packet_line("filter blob:none\n") .
            $this->encode_packet_line("shallow {$ref_hash}\n") .
            $this->encode_packet_line("deepen 1\n") .
            "0000" .
            $this->encode_packet_line("done\n") .
            $this->encode_packet_line("done\n");

        $response = $this->http_request($this->repoUrl . '/git-upload-pack', $body, [
            'Accept: application/x-git-upload-pack-advertisement',
            'Content-Type: application/x-git-upload-pack-request', 
        ]);

        $pack_data = $this->accumulate_pack_data_from_multiplexed_chunks($response);
        return $this->compute_pack_index($pack_data);
    }

    private function fetch_blobs($refs) {
        $body = '';
        foreach($refs as $ref) {
            $body .= $this->encode_packet_line("want {$ref} multi_ack_detailed no-done side-band-64k thin-pack ofs-delta agent=git/2.37.3\n");
        }
        $body .= "0000";
        $body .= $this->encode_packet_line("done\n");

        $response = $this->http_request($this->repoUrl . '/git-upload-pack', $body, [
            'Accept: application/x-git-upload-pack-advertisement',
            'Content-Type: application/x-git-upload-pack-request', 
        ]);
        $pack_data = $this->accumulate_pack_data_from_multiplexed_chunks($response);
        return $this->compute_pack_index($pack_data);
    }

    private function http_request($url, $postData = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function encode_packet_line($data) {
        $length = strlen($data) + 4;
        return str_pad(dechex($length), 4, '0', STR_PAD_LEFT) . $data;
    }

    private function accumulate_pack_data_from_multiplexed_chunks($raw_response) {
        $parsed_pack_data = [];
        $parsed_chunks = $this->parse_multiplexed_pack_data($raw_response);
        foreach($parsed_chunks as $chunk) {
            if($chunk['type'] !== 'side-band') {
                continue;
            }
            $parsed_pack_data[] = $chunk['data'];
        }
        return implode('', $parsed_pack_data);
    }

    private function parse_multiplexed_pack_data($bytes) {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $lengthHex = substr($bytes, $offset, 4);
            $offset += 4;
    
            if ($lengthHex === "0000") {
                continue; // End of packet
            }
    
            $length = hexdec($lengthHex);
            if ($length === 0) {
                break; // No more data
            }
    
            // This is one raw packet line
            $content = substr($bytes, $offset, $length - 4);
            $offset += $length - 4;
    
            // Parse possible multiple side-band chunks inside this single packet line
            $subOffset = 0;
            while ($subOffset < strlen($content)) {
                $channel = $content[$subOffset];
                $subOffset++;
                if ($subOffset >= strlen($content)) {
                    break; // No data left after channel byte
                }
    
                // We'll assume the rest of this line is the sub-chunk’s data
                // (Git typically sends only one sub-chunk per packet line, but
                // if there were multiple, you’d keep parsing until subOffset hits the end)
                $chunkData = substr($content, $subOffset);
                $subOffset = strlen($content);
    
                if ($channel === "\x01") {
                    yield ['type' => 'side-band', 'data' => $chunkData];
                } elseif ($channel === "\x02") {
                    yield ['type' => 'progress', 'data' => $chunkData];
                } elseif ($channel === "\x03") {
                    yield ['type' => 'fatal', 'data' => $chunkData];
                } else {
                    yield ['type' => 'unknown', 'data' => $channel . $chunkData];
                }
            }
        }
    }    

    private function parse_pack_data($packData) {
        $offset = 0;
    
        // Basic sanity checks
        if (strlen($packData) < 12) {
            return false;
        }
    
        $header = substr($packData, $offset, 4);
        $offset += 4;
        if ($header !== "PACK") {
            return false;
        }
    
        $version = unpack('N', substr($packData, $offset, 4))[1];
        $offset += 4;
    
        $objectCount = unpack('N', substr($packData, $offset, 4))[1];
        $offset += 4;
    
        $objects = [];
    
        for ($i = 0; $i < $objectCount; $i++) {
            if ($offset >= strlen($packData)) {
                break;
            }

            $header_offset = $offset;
            $object = $this->parse_pack_header($packData, $offset);
            $object['type_name'] = self::OBJECT_NAMES[$object['type']];
            $object['content_offset'] = $offset;
            $object['header_offset'] = $header_offset;
            $object['content'] = $this->inflate_object($packData, $offset, $object['length']);
            $object['compressed_length'] = $offset - $object['content_offset'];
            $objects[] = $object;
        }
        return [
            'objects' => $objects,
            'total_objects' => $objectCount,
            'pack_version' => $version
        ];
    }

    private function compute_pack_index($pack_data) {
        $parsed_pack = $this->parse_pack_data($pack_data);
        $objects = $parsed_pack['objects'];

        $by_oid = [];
        $by_offset = [];
        $resolved_objects = 0;
        // Index entities and resolve deltas
        // Run until all objects are resolved
        while($resolved_objects < count($objects)) {
            $resolved_in_this_iteration = 0;
            for($i = 0; $i < count($objects); $i++) {
                // Skip already processed objects
                if(
                    isset($by_offset[$objects[$i]['header_offset']]) &&
                    isset($by_oid[$objects[$i]['oid']])
                ) {
                    continue;
                }

                if($objects[$i]['type'] === self::OBJECT_TYPE_OFS_DELTA) {
                    $target_offset = $objects[$i]['header_offset'] - $objects[$i]['ofs'];
                    if(!isset($by_offset[$target_offset])) {
                        continue;
                    }
                    // TODO: Make sure the base object will never be another delta.
                    $base = $objects[$by_offset[$target_offset]];
                    $objects[$i]['content'] = $this->applyDelta($base['content'], $objects[$i]['content']);
                    $objects[$i]['type'] = $base['type'];
                    $objects[$i]['type_name'] = $base['type_name'];
                } else if($objects[$i]['type'] === self::OBJECT_TYPE_REF_DELTA) {
                    if(!isset($by_oid[$objects[$i]['reference']])) {
                        continue;
                    }
                    $base = $objects[$by_oid[$objects[$i]['reference']]];
                    $objects[$i]['content'] = $this->applyDelta($base['content'], $objects[$i]['content']);
                    $objects[$i]['type'] = $base['type'];
                    $objects[$i]['type_name'] = $base['type_name'];
                }
                $oid = sha1($this->wrap_git_object($objects[$i]['type_name'], $objects[$i]['content']));
                $objects[$i]['oid'] = $oid;
                $by_oid[$oid] = $i;
                $by_offset[$objects[$i]['header_offset']] = $i;
                ++$resolved_in_this_iteration;
                ++$resolved_objects;
            }
            if($resolved_in_this_iteration === 0) {
                throw new Exception('Could not resolve objects');
            }
        }

        // Resolve trees
        foreach($objects as $k => $object) {
            if( $object['type'] === self::OBJECT_TYPE_TREE ) {
                $objects[$k]['content'] = $this->parse_tree_bytes($object['content']);
            } else if($object['type'] === self::OBJECT_TYPE_COMMIT) {
                $objects[$k]['tree'] = substr($object['content'], 5, 40);
            }
        }

        return [
            'objects' => $objects,
            'by_oid' => $by_oid,
            'by_offset' => $by_offset,
        ];
    }

    private function wrap_git_object($type, $object) {
        $length = strlen($object);
        return "$type $length\x00" . $object;
    }

    private function parse_pack_header(string $packData, int &$offset) {
        // Object type is encoded in bits 654
        $byte = ord($packData[$offset++]);
        $type = ($byte >> 4) & 0b111;
        // The length encoding get complicated.
        // Last four bits of length is encoded in bits 3210
        $length = $byte & 0b1111;
        // Whether the next byte is part of the variable-length encoded number
        // is encoded in bit 7
        if ($byte & 0b10000000) {
            $shift = 4;
            $byte = ord($packData[$offset++]);
            while ($byte & 0b10000000) {
                $length |= ($byte & 0b01111111) << $shift;
                $shift += 7;
                $byte = ord($packData[$offset++]); 
            }
            $length |= ($byte & 0b01111111) << $shift;
        }
        // Handle deltified objects
        $ofs = null;
        $reference = null;
        if ($type === self::OBJECT_TYPE_OFS_DELTA) {
            // Git uses a specific formula: ofs = ((ofs + 1) << 7) + (c & 0x7f)
            // for each continuation byte. The first byte doesn't do the "ofs+1" part.
            // This code matches Git’s logic.
            $ofs = 0;
            // Read the first byte
            $c = ord($packData[$offset++]);
            $ofs = ($c & 0x7F);

            // If bit 7 (0x80) is set, we keep reading
            while ($c & 0x80) {
                $c = ord($packData[$offset++]);
                $ofs = (($ofs + 1) << 7) + ($c & 0x7F);
            }
        } else if ($type === self::OBJECT_TYPE_REF_DELTA) {
            $reference = substr($packData, $offset, 20);
            $offset += 20;
        }
        return [
            'ofs' => $ofs,
            'type' => $type,
            'length' => $length,
            'reference' => $reference
        ];
    }

    /**
     * Incrementally inflate the next object’s compressed data until it yields 
     * $uncompressedSize bytes, or we hit the end of the compressed stream. 
     * Adjusts $offset so that after returning, $offset points to the next object header.
     */
    private function inflate_object(string $packData, int &$offset, int $uncompressedSize): ?string {
        $inflateContext = inflate_init(ZLIB_ENCODING_DEFLATE);
        if (!$inflateContext) {
            return null;
        }
    
        $inflated = '';
        $packLen = strlen($packData);
    
        $bytes_read = 0;
        while ($offset < $packLen) {
            // Feed chunks into inflate. We don’t know how big each chunk is, 
            // so let's just pick something arbitrary:
            $chunk = substr($packData, $offset, 256);
    
            $res = inflate_add($inflateContext, $chunk);
            switch(inflate_get_status($inflateContext)) {
                case ZLIB_BUF_ERROR:
                case ZLIB_DATA_ERROR:
                case ZLIB_VERSION_ERROR:
                case ZLIB_MEM_ERROR:
                    throw new Exception('Inflate error');
            }
            if ($res === false) {
                throw new Exception('Inflate error');
            }
            $bytes_read_for_this_chunk = inflate_get_read_len($inflateContext) - $bytes_read;
            $offset += $bytes_read_for_this_chunk;

            $bytes_read = inflate_get_read_len($inflateContext);
            $inflated .= $res;
    
            if(inflate_get_status($inflateContext) === ZLIB_STREAM_END) {
                break;
            }
        }
        return $inflated;
    }    
    
    private function parse_tree_bytes($treeContent) {
        $offset = 0;
        $files = [];

        while ($offset < strlen($treeContent)) {
            if ($offset >= strlen($treeContent)) {
                var_dump('uninitialized string offset');
                break; // Prevent uninitialized string offset
            }

            // Read file mode
            $modeEnd = strpos($treeContent, ' ', $offset);
            if ($modeEnd === false || $modeEnd >= strlen($treeContent)) {
                var_dump('invalid mode');
                break; // Invalid mode
            }
            $mode = substr($treeContent, $offset, $modeEnd - $offset);
            $offset = $modeEnd + 1;
            
            if(preg_match('/^0?4.*/', $mode)) {
                $mode = self::FILE_MODE_DIRECTORY;
            } else if(preg_match('/^1006.*/', $mode)) {
                $mode = self::FILE_MODE_REGULAR_NON_EXECUTABLE;
            } else if(preg_match('/^1007.*/', $mode)) {
                $mode = self::FILE_MODE_REGULAR_EXECUTABLE;
            } else if(preg_match('/^120.*/', $mode)) {
                $mode = self::FILE_MODE_SYMBOLIC_LINK;
            } else if(preg_match('/^160.*/', $mode)) {
                $mode = self::FILE_MODE_COMMIT;
            }

            // Read file name
            $nameEnd = strpos($treeContent, "\0", $offset);
            if ($nameEnd === false || $nameEnd >= strlen($treeContent)) {
                var_dump('invalid name');
                break; // Invalid name
            }
            $name = substr($treeContent, $offset, $nameEnd - $offset);
            $offset = $nameEnd + 1;

            // Read SHA1
            if ($offset + 20 > strlen($treeContent)) {
                var_dump('invalid sha1');
                break; // Prevent out-of-bounds access
            }
            $sha1 = bin2hex(substr($treeContent, $offset, 20));
            $offset += 20;

            $files[$name] = [
                'mode' => $mode,
                'name' => $name,
                'sha1' => $sha1,
            ];
        }

        return $files;
    }

    private function applyDelta($base_bytes, $delta_bytes) {
        $offset = 0;

        $base_size = $this->readVariableLength($delta_bytes, $offset);
        if($base_size !== strlen($base_bytes)) {
            // @TODO: Do not throw exceptions...? Or do?
            throw new Exception('Base size mismatch');
        }
        $result_size = $this->readVariableLength($delta_bytes, $offset);

        $result = '';
        while ($offset < strlen($delta_bytes)) {
            $byte = ord($delta_bytes[$offset++]);
            if ($byte & 0x80) {
                $copyOffset = 0;
                $copySize = 0;
                if ($byte & 0x01) $copyOffset |= ord($delta_bytes[$offset++]);
                if ($byte & 0x02) $copyOffset |= ord($delta_bytes[$offset++]) << 8;
                if ($byte & 0x04) $copyOffset |= ord($delta_bytes[$offset++]) << 16;
                if ($byte & 0x08) $copyOffset |= ord($delta_bytes[$offset++]) << 24;
                if ($byte & 0x10) $copySize |= ord($delta_bytes[$offset++]);
                if ($byte & 0x20) $copySize |= ord($delta_bytes[$offset++]) << 8;
                if ($byte & 0x40) $copySize |= ord($delta_bytes[$offset++]) << 16;
                if ($copySize === 0) $copySize = 0x10000;
                $result .= substr($base_bytes, $copyOffset, $copySize);
            } else {
                $result .= substr($delta_bytes, $offset, $byte);
                $offset += $byte;
            }
        }

        if(strlen($result) !== $result_size) {
            // @TODO: Do not throw exceptions...? Or do?
            throw new Exception('Result size mismatch');
        }

        return $result;
    }

    private function readVariableLength($data, &$offset) {
        $result = 0;
        $shift = 0;
        do {
            $byte = ord($data[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);
        return $result;
    }
}


// $client = new WP_Git_Index('https://github.com/WordPress/gutenberg.git');
$client = new WP_Git_Index('https://github.com/adamziel/playground-docs-workflow.git');
$files = $client->fetchFiles();

if ($files === false) {
    echo "Failed to fetch repository files.\n";
    exit;
}

// Filter for the `/docs` directory
$docsDirectory = [];
foreach ($files as $path => $content) {
    if (strpos($path, 'docs/') === 0) {
        $docsDirectory[$path] = $content;
    }
}

// Display the files in the `/docs` directory
if (!empty($docsDirectory)) {
    foreach ($docsDirectory as $filePath => $fileContent) {
        echo "File: $filePath\n";
        echo "Content:\n$fileContent\n\n";
    }
} else {
    echo "No files found in the /docs directory.\n";
}