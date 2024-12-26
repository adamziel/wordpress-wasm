<?php

class WP_Git_Pack_Index {

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

    private $objects = [];
    private $by_oid = [];
    private $external_get_by_oid = null;

    private function __construct(
        $objects = [],
        $by_oid = []
    ) {
        $this->objects = $objects;
        $this->by_oid = $by_oid;
    }

    public function set_external_get_by_oid($external_get_by_oid) {
        $this->external_get_by_oid = $external_get_by_oid;
    }

    public function get_by_oid($oid) {
        if(isset($this->by_oid[$oid])) {
            return $this->objects[$this->by_oid[$oid]];
        }
        if($this->external_get_by_oid) {
            $factory = $this->external_get_by_oid;
            return $factory($oid);
        }
        return null;
    }

    public function get_by_path($path, $root_tree_oid=null) {
        if($root_tree_oid === null) {
            foreach($this->objects as $object) {
                if($object['type'] === self::OBJECT_TYPE_COMMIT) {
                    $root_tree_oid = $object['tree'];
                    break;
                }
            }
        }
        $current_tree = $this->get_by_oid($root_tree_oid);
        if (!$current_tree) {
            return null;
        }

        if($current_tree['type'] === self::OBJECT_TYPE_COMMIT) {
            $current_tree = $this->get_by_oid($current_tree['tree']);
        }

        $path = trim($path, '/');
        if (empty($path)) {
            return $current_tree;
        }

        $path_segments = explode('/', $path);
        foreach ($path_segments as $segment) {
            if (!isset($current_tree['content'][$segment])) {
                return null;
            }
            $next_oid = $current_tree['content'][$segment]['sha1'];
            $current_tree = $this->get_by_oid($next_oid);
            if (!$current_tree) {
                return null;
            }
        }

        return $current_tree;
    }

    public function get_descendants($tree_oid) {
        $tree = $this->get_by_oid($tree_oid);
        if (!$tree || !isset($tree['content'])) {
            return [];
        }
        foreach ($tree['content'] as $name => $object) {
            if ($object['mode'] === self::FILE_MODE_DIRECTORY) {
                yield from $this->get_descendants($object['sha1']);
            } else {
                yield $object;
            }
        }
    }

    public function get_descendants_tree($tree_oid) {
        $tree = $this->get_by_oid($tree_oid);
        if (!$tree || !isset($tree['content'])) {
            return [];
        }

        $descendants = [];
        foreach ($tree['content'] as $name => $object) {
            if ($object['mode'] === self::FILE_MODE_DIRECTORY) {
                $descendants[$name] = $this->get_descendants_tree($object['sha1']);
            } else {
                $blob = $this->get_by_oid($object['sha1']);
                $descendants[$name] = isset($blob['content']) ? $blob['content'] : null;
            }
        }

        return $descendants;
    }

    static public function from_pack_data($pack_data) {
        $parsed_pack = self::parse_pack_data($pack_data);
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
                    $objects[$i]['content'] = self::applyDelta($base['content'], $objects[$i]['content']);
                    $objects[$i]['type'] = $base['type'];
                    $objects[$i]['type_name'] = $base['type_name'];
                } else if($objects[$i]['type'] === self::OBJECT_TYPE_REF_DELTA) {
                    if(!isset($by_oid[$objects[$i]['reference']])) {
                        continue;
                    }
                    $base = $objects[$by_oid[$objects[$i]['reference']]];
                    $objects[$i]['content'] = self::applyDelta($base['content'], $objects[$i]['content']);
                    $objects[$i]['type'] = $base['type'];
                    $objects[$i]['type_name'] = $base['type_name'];
                }
                $oid = sha1(self::wrap_git_object($objects[$i]['type_name'], $objects[$i]['content']));
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
                $objects[$k]['content'] = self::parse_tree_bytes($object['content']);
            } else if($object['type'] === self::OBJECT_TYPE_COMMIT) {
                $objects[$k]['tree'] = substr($object['content'], 5, 40);
            }
        }

        return new WP_Git_Pack_Index(
            $objects,
            $by_oid
        );
    }

    static private function wrap_git_object($type, $object) {
        $length = strlen($object);
        return "$type $length\x00" . $object;
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

    static private function parse_tree_bytes($treeContent) {
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

    static private function parse_pack_data($packData) {
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
            $object['type_name'] = self::OBJECT_NAMES[$object['type']];
            $object['content_offset'] = $offset;
            $object['header_offset'] = $header_offset;
            $object['content'] = self::inflate_object($packData, $offset, $object['length']);
            $object['compressed_length'] = $offset - $object['content_offset'];
            $objects[] = $object;
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

} 