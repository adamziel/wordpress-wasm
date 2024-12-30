<?php

use WordPress\AsyncHttp\Client;
use WordPress\AsyncHttp\Request;
use WordPress\ByteReader\WP_String_Reader;

class WP_Git_Client {
    /**
     * @var Client
     */
    private $http_client;
    /**
     * @var WP_Git_Repository
     */
    private $index;
    private $remote_name = 'origin';

    public function __construct(WP_Git_Repository $index, $options = []) {
        $this->remote_name = $options['remote_name'] ?? 'origin';
        $this->http_client = $options['http_client'] ?? new Client();
        $this->index = $index;
    }

    public function fetchRefs($prefix) {
        $response = $this->http_request(
            '/git-upload-pack',
            $this->encode_packet_line("command=ls-refs\n") .
            $this->encode_packet_line("agent=git/2.37.3\n") .
            $this->encode_packet_line("object-format=sha1\n") .
            "0001" .
            $this->encode_packet_line("peel\n") .
            $this->encode_packet_line("ref-prefix $prefix\n") .
            "0000",
            [
                'Accept' => 'application/x-git-upload-pack-advertisement',
                'Content-Type' => 'application/x-git-upload-pack-request', 
                'Git-Protocol' => 'version=2'
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

            $localized_refname = $name;
            if(str_starts_with($name, 'refs/heads/')) {
                $localized_refname = substr($name, strlen('refs/heads/'));
            }
            $this->index->set_ref_head('refs/remotes/' . $this->remote_name . '/' . $localized_refname, $hash);
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

    public function force_push_one_commit() {
        $push_ref_name = $this->index->get_ref_head('HEAD', ['resolve_ref' => false]);
        $push_ref_name = $this->localize_ref_name($push_ref_name);

        $push_commit = $this->index->get_ref_head('refs/heads/' . $push_ref_name);
        $this->index->read_object($push_commit);
        $parent_hash = $this->index->get_parsed_commit()['parent'] ?? '0000000000000000000000000000000000000000';

        $remote_commit = $this->index->get_ref_head('refs/remotes/' . $this->remote_name . '/' . $push_ref_name);
        // @TODO: Do find_objects_added_since to enable pushing multiple commits at once.
        //        OR! perhaps supporting "have" and "want" would solve this.
        $delta = $this->index->find_objects_added_in($push_commit, $remote_commit);

        // @TODO: Implement streaming push bytes instead of buffering everything like this.
        $pack_objects = [];
        foreach($delta as $oid) {
            // @TODO: just stream the saved object instead of re-reading and re-encoding it.
            $body = '';
            do {
                $body .= $this->index->get_body_chunk();
            } while($this->index->next_body_chunk());
            $pack_objects[] = [
                'type' => $this->index->get_type(),
                'content' => $body,
            ];
        }

        $push_packet = WP_Git_Pack_Processor::encode_packet_line("$parent_hash $push_commit refs/heads/$push_ref_name\0report-status force-update\n");
        $push_packet .= "0000";
        $push_packet .= WP_Git_Pack_Processor::encode($pack_objects);
        $push_packet .= "0000";

        $response = $this->http_request('/git-receive-pack', $push_packet, [
            'Content-Type' => 'application/x-git-receive-pack-request',
            'Accept' => 'application/x-git-receive-pack-result',
        ]);

        $response_chunks = iterator_to_array($this->parse_multiplexed_pack_data($response));
        if(
            trim($response_chunks[0]['data']) !== 'unpack ok' ||
            trim($response_chunks[1]['data']) !== 'ok refs/heads/' . $push_ref_name
        ) {
            throw new Exception('Push failed:' . $response);
        }
        $this->index->set_ref_head('refs/remotes/' . $this->remote_name . '/' . $push_ref_name, $push_commit);
        return true;
    }

    private function localize_ref_name($ref_name) {
        if(str_starts_with($ref_name, 'ref: ')) {
            $ref_name = trim(substr($ref_name, 5));
        }
        if(str_starts_with($ref_name, 'refs/heads/')) {
            return substr($ref_name, strlen('refs/heads/'));
        }
        return $ref_name;
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

        $response = $this->http_request('/git-upload-pack', $body, [
            'Accept: application/x-git-upload-pack-advertisement',
            'Content-Type: application/x-git-upload-pack-request', 
        ]);

        $pack_data = $this->accumulate_pack_data_from_multiplexed_chunks($response);
        return WP_Git_Pack_Processor::decode($pack_data);
    }
    
    public function force_pull($branch_name, $path = '/') {
        $path = '/' . ltrim($path, '/');
        $remote_refs = $this->fetchRefs('refs/heads/' . $branch_name);
        $remote_head = $remote_refs['refs/heads/' . $branch_name];
        $remote_index = $this->list_objects($remote_head);

        $remote_branch_ref = 'refs/heads/' . $branch_name;
        $remote_index->set_ref_head($remote_branch_ref, $remote_head);
        $remote_index->set_ref_head('HEAD', 'ref: ' . $remote_branch_ref);

        $local_index = $this->index;
        $local_ref = $local_index->get_ref_head('refs/heads/' . $branch_name);

        $all_path_related_oids = $remote_index->find_path_descendants($path);
        $subpath = $path;
        do {
            $subpath = dirname($subpath);
            $remote_index->read_by_path($subpath);
            $all_path_related_oids[] = $remote_index->get_oid();
        } while($subpath !== '/');
        $all_path_related_oids[] = $remote_head;
        $all_path_related_oids = array_flip($all_path_related_oids);

        // @TODO: Support "want" and "have" here
        $new_oids = $remote_index->find_objects_added_in($remote_head, $local_ref, [
            'old_tree_index' => $local_index,
        ]);
        $objects_to_fetch = [];
        foreach($new_oids as $oid) {
            if(!isset($all_path_related_oids[$oid])) {
                continue;
            }
            $objects_to_fetch[] = $oid;
        }
        $this->fetchObjects($objects_to_fetch);
        $this->index->set_ref_head('refs/heads/' . $branch_name, $remote_head);
    }

    public function fetchObjects($refs) {
        $body = '';
        foreach($refs as $ref) {
            $body .= $this->encode_packet_line("want {$ref} multi_ack_detailed no-done side-band-64k thin-pack ofs-delta agent=git/2.37.3\n");
        }
        $body .= "0000";
        $body .= $this->encode_packet_line("done\n");

        $response = $this->http_request('/git-upload-pack', $body, [
            'Accept: application/x-git-upload-pack-advertisement',
            'Content-Type: application/x-git-upload-pack-request', 
        ]);
        $pack_data = $this->accumulate_pack_data_from_multiplexed_chunks($response);
        WP_Git_Pack_Processor::decode($pack_data, $this->index);
        return true;
    }

    public function get_last_error() {
        $last_request = $this->http_client->get_request();
        if(!$last_request) {
            return null;
        }
        return $last_request->error;
    }

    private function http_request($path, $postData = null, $headers = []) {
        $remote = $this->index->get_remote($this->remote_name);
        if(!$remote) {
            $this->last_error = 'Remote "' . $this->remote_name . '" not found';
            return false;
        }
        $url = $remote['url'] . $path;
        $request_info = [];
        if($postData) {
            $request_info['headers'] = $headers;
            $request_info['method'] = 'POST';
            $request_info['body_stream'] = WP_String_Reader::create($postData);
        }
        $request = new Request($url, $request_info);
        $this->http_client->enqueue($request);

        $buffered_response = '';
        while($this->http_client->await_next_event()) {
            $event = $this->http_client->get_event();
            switch($event) {
                case Client::EVENT_BODY_CHUNK_AVAILABLE:
                    $buffered_response .= $this->http_client->get_response_body_chunk();
                    break;
                case Client::EVENT_FAILED:
                    return false;
            }
        }
        return $buffered_response;
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
    
}
