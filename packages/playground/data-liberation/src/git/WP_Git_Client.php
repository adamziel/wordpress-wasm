<?php

require_once __DIR__ . '/WP_Git_Pack_Index.php';

class WP_Git_Client {
    private $repoUrl;

    public function __construct($repoUrl) {
        $this->repoUrl = rtrim($repoUrl, '/');
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

}
