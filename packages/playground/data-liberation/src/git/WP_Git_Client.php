<?php

require_once __DIR__ . '/WP_Git_Pack_Index.php';

class WP_Git_Client {
    private $repoUrl;
    private $author;
    private $committer;

    public function __construct($repoUrl, $options = []) {
        $this->repoUrl = rtrim($repoUrl, '/');
        $this->author = $options['author'] ?? "John Doe <john@example.com>";
        $this->committer = $options['committer'] ?? "John Doe <john@example.com>";
    }

    public function fetchRefs($prefix) {
        $url = $this->repoUrl . '/git-upload-pack';
        $response = $this->http_request(
            $url,
            $this->encode_packet_line("command=ls-refs\n") .
            $this->encode_packet_line("agent=git/2.37.3\n") .
            $this->encode_packet_line("object-format=sha1\n") .
            "0001" .
            $this->encode_packet_line("peel\n") .
            $this->encode_packet_line("ref-prefix $prefix\n") .
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

        $refs = [];
        foreach ($this->parse_git_protocol_v2_packets($response) as $frame) {
            $space_pos = strpos($frame, ' ');
            if($space_pos === false) {
                continue;
            }
            $hash = substr($frame, 0, $space_pos);
            $newline_pos = strpos($frame, "\n");
            $name = substr($frame, $space_pos + 1, $newline_pos - $space_pos - 1);
            $refs[$name] = $hash;
        }
        return $refs;
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

    public function push($git_objects, $options = []) {
        $empty_hash = "0000000000000000000000000000000000000000";
        $parent_hash = $options['parent_hash'] ?? $empty_hash;
        $tree_hash = $options['tree_hash'] ?? $empty_hash;
        $branchName = $options['branch_name'];
        $author = ($options['author'] ?? $this->author) . " " . time() . " +0000";
        $committer = ($options['committer'] ?? $this->committer) . " " . time() . " +0000";
        $message = $options['message'] ?? "Hello!";

        $parent = '';
        if($parent_hash !== $empty_hash) {
            $parent = "parent $parent_hash\n";
        }
        $commit_object = WP_Git_Pack_Processor::create_object([
            'type' => WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT,
            'content' => sprintf(
                "tree %s\n%sauthor %s\ncommitter %s\n\n%s\n",
                $tree_hash,
                $parent,
                $author,
                $committer,
                $message
            ),
            'tree' => $tree_hash,
        ]);
        $commit_sha = $commit_object['oid'];

        $git_objects[] = $commit_object;
        
        $push_packet = WP_Git_Pack_Processor::encode_packet_line("$parent_hash $commit_sha refs/heads/$branchName\0report-status force-update\n");
        $push_packet .= "0000";
        $push_packet .= WP_Git_Pack_Processor::encode($git_objects);
        $push_packet .= "0000";

        $url = rtrim($this->repoUrl, '.git').'.git/git-receive-pack';
        $response = $this->http_request($url, $push_packet, [
            'Content-Type: application/x-git-receive-pack-request',
            'Accept: application/x-git-receive-pack-result',
        ]);

        $response_chunks = iterator_to_array($this->parse_multiplexed_pack_data($response));
        if(
            trim($response_chunks[0]['data']) !== 'unpack ok' ||
            trim($response_chunks[1]['data']) !== 'ok refs/heads/' . $branchName
        ) {
            throw new Exception('Push failed:' . $response);
        }
        return [
            'new_head_hash' => $commit_sha,
            'new_tree_hash' => $tree_hash,
        ];
    }

    public function list_objects($ref_hash) {
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
        return WP_Git_Pack_Index::from_pack_data($pack_data);
    }
    
    public function fetchObjects($refs) {
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
        return WP_Git_Pack_Index::from_pack_data($pack_data);
    }

    public function backfillBlobs($index, $root = '/') {
        $sub_root = $index->get_by_path($root);

        $blobs_shas = [];
        foreach($index->get_descendants($sub_root['oid']) as $blob) {
            $blobs_shas[] = $blob['sha1'];
        }
        $blobs_index = $this->fetchObjects($blobs_shas);
        $index->set_external_get_by_oid(function($oid) use ($blobs_index) {
            return $blobs_index->get_by_oid($oid);
        });
        return $index;
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

    static public function parse_multiplexed_pack_data($bytes) {
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

    private const DELETE_PLACEHOLDER = 'DELETE_PLACEHOLDER';

    /**
     * Computes Git objects needed to commit a changeset.
     * 
     * @param WP_Git_Pack_Processor $oldIndex The index containing existing objects
     * @param WP_Changeset $changeset The changes to commit
     * @return string The Git objects with type, content and SHA
     */
    public function compute_push_objects(
        WP_Git_Pack_Index $index,
        WP_Changeset $changeset,
        $branchName,
        $parent_hash = null
    ) {
        $new_index = [];

        $new_tree = new stdClass();
        foreach (array_merge($changeset->create, $changeset->update) as $path => $content) {
            $new_blob = WP_Git_Pack_Processor::create_object([
                'type' => WP_Git_Pack_Processor::OBJECT_TYPE_BLOB,
                'content' => $content,
            ]);
            $new_index[] = $new_blob;
            $this->set_oid($new_tree, $path, $new_blob['oid']);
        }
        
        foreach ($changeset->delete as $path) {
            $this->set_oid($new_tree, $path, self::DELETE_PLACEHOLDER);
        }

        if(!$parent_hash) {
            $parent_hash = "0000000000000000000000000000000000000000";
        }
        $parent = '';
        if($parent_hash) {
            $parent = "parent $parent_hash\n";
        }
        $root_tree = $this->backfill_trees($index, $new_index, $new_tree, '/');
        $commit = WP_Git_Pack_Processor::create_object([
            'type' => WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT,
            'content' => sprintf(
                "tree %s\n{$parent}author %s\ncommitter %s\n\n%s\n",
                $root_tree['oid'],
                "John Doe <john@example.com> " . time() . " +0000",
                "John Doe <john@example.com> " . time() . " +0000",
                "Hello!"
            ),
            'tree' => $root_tree['oid'],
        ]);
        $commit_sha = $commit['oid'];
        $new_index[] = $commit;
        // Make $new_index unique by 'oid' column
        $seen_oids = [];
        $new_index = array_filter($new_index, function($obj) use (&$seen_oids) {
            if (isset($seen_oids[$obj['oid']])) {
                return false;
            }
            $seen_oids[$obj['oid']] = true;
            return true;
        });
        // $new_index = array_reverse($new_index);

        var_dump($new_index);

        $pktPush = WP_Git_Pack_Processor::encode_packet_line("$parent_hash $commit_sha refs/heads/$branchName\0report-status force-update\n");
        $pktPush .= "0000";
        $pktPush .= WP_Git_Pack_Processor::encode($new_index);
        $pktPush .= "0000";
    
        return $pktPush;
    }

    private function backfill_trees(WP_Git_Pack_Index $current_index, &$new_index, $subtree_delta, $subtree_path = '/') {
        $subtree_path = ltrim($subtree_path, '/');
        $new_tree_content = [];

        $indexed_tree = $current_index->get_by_path($subtree_path);
        if($indexed_tree) {
            foreach($indexed_tree['content'] as $object) {
                // Backfill the unchanged objects from the currently indexed subtree.
                $name = $object['name'];
                if(!isset($subtree_delta->children[$name])) {
                    $new_tree_content[$name] = $object;
                }
            }
        }

        // Index changed and new objects in the current subtree.
        foreach($subtree_delta->children as $name => $subtree_child) {
            // Ignore any deleted objects.
            if(isset($subtree_child->oid) && $subtree_child->oid === self::DELETE_PLACEHOLDER) {
                continue;
            }

            // Index blobs
            switch($subtree_child->type) {
                case WP_Git_Pack_Processor::OBJECT_TYPE_BLOB:
                    $new_tree_content[$name] = [
                        'mode' => WP_Git_Pack_Processor::FILE_MODE_REGULAR_NON_EXECUTABLE,
                        'name' => $name,
                        'sha1' => $subtree_child->oid,
                    ];
                    break;
                case WP_Git_Pack_Processor::OBJECT_TYPE_TREE:
                    $subtree_object = $this->backfill_trees($current_index, $new_index, $subtree_child, $subtree_path . '/' . $name);
                    $new_tree_content[$name] = [
                        'mode' => WP_Git_Pack_Processor::FILE_MODE_DIRECTORY,
                        'name' => $name,
                        'sha1' => $subtree_object['oid'],
                    ];
                    break;
            }
        }

        $new_tree_object = WP_Git_Pack_Processor::create_object([
            'type' => WP_Git_Pack_Processor::OBJECT_TYPE_TREE,
            'content' => $new_tree_content,
        ]);

        $new_index[] = $new_tree_object;
        return $new_tree_object;
    }

    private function set_oid($root_tree, $path, $oid) {
        $blob = new stdClass();
        $blob->type = WP_Git_Pack_Processor::OBJECT_TYPE_BLOB;
        $blob->oid = $oid;

        $subtree_path = dirname($path);
        if($subtree_path === '.') {
            $subtree = $root_tree;
        } else {
            $subtree = $this->get_subtree($root_tree, $subtree_path);
        }
        $filename = basename($path);
        $subtree->children[$filename] = $blob;
    }

    private function get_subtree($root_tree, $path) {
        $path = trim($path, '/');
        $segments = explode('/', $path);
        $subtree = $root_tree;
        foreach ($segments as $segment) {
            if (!isset($subtree->children[$segment])) {
                $new_subtree = new stdClass();
                $new_subtree->type = WP_Git_Pack_Processor::OBJECT_TYPE_TREE;
                $new_subtree->children = [];
                $subtree->children[$segment] = $new_subtree;
            }
            $subtree = $subtree->children[$segment];
        }
        return $subtree;
    }
    
}
