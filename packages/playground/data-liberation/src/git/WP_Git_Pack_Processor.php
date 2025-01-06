<?php

use WordPress\Filesystem\WP_In_Memory_Filesystem;

class WP_Git_Pack_Processor {

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

    // Helper: Build a barebones pack with the given objects (no compression).
    // Objects must be in an order that satisfies dependencies if you skip deltas.
    static public function encode(array $objects): string {
        // PACK header: 4-byte signature, 4-byte version=2, 4-byte object count
        $pack = "PACK";
        $pack .= pack("N", 2);              // version
        $pack .= pack("N", count($objects)); // number of objects
    
        foreach ($objects as $obj) {
            if(
                $obj['type'] === WP_Git_Pack_Processor::OBJECT_TYPE_TREE &&
                is_array($obj['content'])
            ) {
                $obj['content'] = WP_Git_Pack_Processor::encode_tree_bytes($obj['content']);
            }
            $pack .= self::object_header($obj['type'], strlen($obj['content']));
            $pack .= self::deflate($obj['content']);
        }
    
        // Then append a 20-byte trailing pack checksum (SHA1 of all preceding bytes).
        $packSha = sha1($pack, true);
        return $pack . $packSha;
    }

    static public function deflate(string $content): string {
        $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        return deflate_add($context, $content, ZLIB_FINISH);
    }

    static public function inflate(string $content): string {
        $context = inflate_init(ZLIB_ENCODING_DEFLATE);
        return inflate_add($context, $content, ZLIB_FINISH);
    }

    static public function object_header(int $type, int $size): string {
        // First byte: type in bits 4-6, size bits 0-3
        $firstByte = $size & 0b1111;
        $firstByte |= ($type & 0b111) << 4;
        // Continuation bit 7 if needed
        if($size > 15 ) {
            $firstByte |= 0b10000000;
        }
        
        // Get remaining size bits after first 4 bits
        $remainingSize = $size >> 4;
        
        // Build result starting with first byte
        $result = chr($firstByte);
        // Add continuation bytes if needed
        while ($remainingSize > 0) {
            // Set continuation bit if we have more bytes
            $byte = $remainingSize & 0b01111111;
            $remainingSize >>= 7;
            if($remainingSize > 0) {
                $byte |= 0b10000000;
            }
            
            $result .= chr($byte);
        }
        
        return $result;
    }

    /**
     * @param $tree array{
     *     array {
     *         $mode: string,
     *         $name: string,
     *         $sha1: string,
     *     }
     * }
     */
    static public function encode_tree_bytes($tree) {
        $tree_bytes = '';
        foreach ($tree as $value) {
            $tree_bytes .= $value['mode'] . " " . $value['name'] . "\0" . hex2bin($value['sha1']);
        }
        return $tree_bytes;
    }

    static public function create_object($basic_data) {
        $object = [
            'ofs' => 0,
            'type' => $basic_data['type'],
            'reference' => null,
            'header_offset' => 0,
        ];
        switch($basic_data['type']) {
            case WP_Git_Pack_Processor::OBJECT_TYPE_BLOB:
                $object['content'] = $basic_data['content'];
                $object['oid'] = sha1(self::wrap_object($basic_data['type'], $basic_data['content']));
                break;
            case WP_Git_Pack_Processor::OBJECT_TYPE_TREE:
                $object['content'] = [];
                foreach($basic_data['content'] as $value) {
                    $object['content'][$value['name']] = $value;
                }
                ksort($object['content']);
                $encoded_bytes = self::encode_tree_bytes($object['content']);
                $object['oid'] = sha1(self::wrap_object($basic_data['type'], $encoded_bytes));
                break;
            case WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT:
                $object['content'] = $basic_data['content'];
                $object['tree'] = $basic_data['tree'];
                $object['oid'] = sha1(self::wrap_object($basic_data['type'], $basic_data['content']));
                break;
        }
        return $object;
    }

    static private function wrap_object($type, $object) {
        $length = strlen($object);
        $type_name = WP_Git_Pack_Processor::OBJECT_NAMES[$type];
        return "$type_name $length\x00" . $object;
    }

    static public function encode_packet_lines(array $payloads): string {
        $lines = [];
        foreach($payloads as $payload) {
            $lines[] = self::encode_packet_line($payload);
        }
        return implode('', $lines);
    }

    static public function encode_packet_line(string $payload): string {
        if($payload === '0000' || $payload === '0001' || $payload === '0002') {
            return $payload;
        }
        $length = strlen($payload) + 4;
        return sprintf("%04x", $length) . $payload;
    }
    
    /**
     * @TODO: A streaming version of this would enable cloning large repositories
     *        without crashing when we run out of memory.
     */
    static public function decode($pack_bytes, $pack_index=null) {
        if(null === $pack_index) {
            $pack_index = new WP_Git_Repository(new WP_In_Memory_Filesystem());
        }

        $parsed_pack = self::parse_pack_data($pack_bytes);

        // Resolve trees
        foreach($parsed_pack['objects'] as $object) {
            $pack_index->add_object($object['type'], $object['content']);
        }

        return $pack_index;
    }

    static public function decode_next_packet_line($pack_bytes, &$offset) {
        $packet_length_bytes = substr($pack_bytes, $offset, 4);
        $offset += 4;
        if(
            strlen($packet_length_bytes) !== 4 ||
            !preg_match('/^[0-9a-f]{4}$/', $packet_length_bytes)
        ) {
            return false;
        }
        switch($packet_length_bytes) {
            case '0000':
                return ['type' => '#flush'];
            case '0001':
                return ['type' => '#delimiter'];
            case '0002':
                return ['type' => '#response-end'];
            default:
                $length = intval($packet_length_bytes, 16) - 4 ;
                $payload = substr($pack_bytes, $offset, $length);
                if(str_ends_with($payload, "\n")) {
                    $payload = substr($payload, 0, -1);
                }
                $offset += $length;
                return ['type' => '#packet', 'payload' => $payload];
        }
    }

    static private function wrap_git_object($type, $object) {
        $length = strlen($object);
        $type_name = self::OBJECT_NAMES[$type];
        return "$type_name $length\x00" . $object;
    }

    static private function applyDelta($base_bytes, $delta_bytes) {
        $offset = 0;

        $base_size = self::readVariableLength($delta_bytes, $offset);
        if($base_size !== strlen($base_bytes)) {
            // @TODO: Do not throw exceptions...? Or do?
            throw new Exception('Base size mismatch');
        }
        $result_size = self::readVariableLength($delta_bytes, $offset);

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

    static public function parse_commit_body($commit_message) {
        $lines = explode("\n", $commit_message);
        $parsed = [];
        foreach($lines as $k => $line) {
            if(!trim($line)) {
                $parsed['message'] = implode("\n", array_slice($lines, $k + 1));
                break;
            }
            $type_len = strpos($line, ' ');
            $type = substr($line, 0, $type_len);
            $value = substr($line, $type_len + 1);

            if($type === 'author') {
                $author_date_starts = strpos($value, '>') + 1;
                $parsed['author'] = substr($value, 0, $author_date_starts);
                $parsed['author_date'] = substr($value, $author_date_starts + 1);
            } else if($type === 'committer') {
                $committer_date_starts = strpos($value, '>') + 1;
                $parsed['committer'] = substr($value, 0, $committer_date_starts);
                $parsed['committer_date'] = substr($value, $committer_date_starts + 1);
            } else {
                $parsed[$type] = $value;
            }
        }
        return $parsed;
    }

    static public function parse_tree_bytes($treeContent) {
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

    static private function readVariableLength($data, &$offset) {
        $result = 0;
        $shift = 0;
        do {
            $byte = ord($data[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);
        return $result;
    }

    static public function parse_pack_data($packData) {
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
            $object = self::parse_pack_header($packData, $offset);
            $object['header_offset'] = $header_offset;
            $object['content'] = self::inflate_object($packData, $offset, $object['uncompressed_length']);
            $objects[] = $object;
        }

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
                    $objects[$i]['content'] = self::applyDelta($base['content'], $objects[$i]['content']);
                    $objects[$i]['type'] = $base['type'];
                } else if($objects[$i]['type'] === self::OBJECT_TYPE_REF_DELTA) {
                    if(!isset($by_oid[$objects[$i]['reference']])) {
                        continue;
                    }
                    $base = $objects[$by_oid[$objects[$i]['reference']]];
                    $objects[$i]['content'] = self::applyDelta($base['content'], $objects[$i]['content']);
                    $objects[$i]['type'] = $base['type'];
                }
                $oid = sha1(self::wrap_git_object($objects[$i]['type'], $objects[$i]['content']));
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
        return [
            'objects' => $objects,
            'total_objects' => $objectCount,
            'pack_version' => $version
        ];
    }

    /**
     * Incrementally inflate the next object’s compressed data until it yields 
     * $uncompressedSize bytes, or we hit the end of the compressed stream. 
     * Adjusts $offset so that after returning, $offset points to the next object header.
     */
    static private function inflate_object(string $packData, int &$offset, int $uncompressedSize): ?string {
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

    static private function parse_pack_header(string $packData, int &$offset) {
        // Object type is encoded in bits 654
        $byte = ord($packData[$offset++]);
        $type = ($byte >> 4) & 0b111;
        // The length encoding get complicated.
        // Last four bits of length is encoded in bits 3210
        $uncompressed_length = $byte & 0b1111;
        // Whether the next byte is part of the variable-length encoded number
        // is encoded in bit 7
        if ($byte & 0b10000000) {
            $shift = 4;
            $byte = ord($packData[$offset++]);
            while ($byte & 0b10000000) {
                $uncompressed_length |= ($byte & 0b01111111) << $shift;
                $shift += 7;
                $byte = ord($packData[$offset++]); 
            }
            $uncompressed_length |= ($byte & 0b01111111) << $shift;
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
            'uncompressed_length' => $uncompressed_length,
            'reference' => $reference
        ];
    }
    
}
