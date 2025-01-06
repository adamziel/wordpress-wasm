<?php

use WordPress\AsyncHttp\ResponseWriter\ResponseWriter;

/**
 * Implement Git server protocol v2
 * https://git-scm.com/docs/protocol-v2
 */
class WP_Git_Server {
    /**
     * @var WP_Git_Repository
     */
    private $repository;

    public function __construct(WP_Git_Repository $repository) {
        $this->repository = $repository;
    }

    public function handle_request($path, $request_bytes, $response) {
        switch($path) {
            case '/HEAD':
                $response->write(WP_Git_Pack_Processor::encode_packet_line(sha1("a") . " HEAD\n"));
                $response->write(WP_Git_Pack_Processor::encode_packet_line("0000"));
                $response->end();
                break;
            // @TODO handle service=git-upload-pack
            case '/info/refs?service=git-upload-pack':
                $parsed = $this->parse_message($request_bytes);
                $response->send_header(
                    'Content-Type',
                    'application/x-git-upload-pack-advertisement'
                );
                $response->send_header(
                    'Cache-Control',
                    'no-cache'
                );
                $response->send_header(
                    'Git-Protocol',
                    'version=2'
                );
                $response->write(WP_Git_Pack_Processor::encode_packet_lines([
                    "# service=git-upload-pack\n",
                    "0000",
                    "version 2\n",
                    "agent=git/github-395dce4f6ecf\n",
                    "ls-refs=unborn\n",
                    "fetch=shallow wait-for-done filter\n",
                    "server-option\n",
                    "object-format=sha1\n",
                    "0000"
                ]));
                flush();
                $response->end();
                break;
            case '/git-upload-pack':
                $parsed = $this->parse_message($request_bytes);
                switch($parsed['capabilities']['command']) {
                    case 'ls-refs':
                        $this->handle_ls_refs_request($request_bytes, $response);
                        break;
                    case 'fetch':
                        $this->handle_fetch_request($request_bytes, $response);
                        break;
                    default:
                        throw new Exception('Unknown command: ' . $parsed['capabilities']['command']);
                }
                break;
            case '/git-receive-pack':
                throw new Exception('Not implemented yet');
            default:
                throw new Exception('Unknown path: ' . $path);
        }
    }

    /**
     * Handle Git protocol v2 ls-refs command
     * 
     * ls-refs is the command used to request a reference advertisement in v2.
     * Unlike the current reference advertisement, ls-refs takes in arguments
     * which can be used to limit the refs sent from the server.
     * 
     * Additional features not supported in the base command will be advertised as
     * the value of the command in the capability advertisement in the form of a space
     * separated list of features: "<command>=<feature 1> <feature 2>"
     * 
     * ls-refs takes in the following arguments:
     * 
     * symrefs
     * In addition to the object pointed by it, show the underlying ref
     * pointed by it when showing a symbolic ref.
     * 
     * peel
     * Show peeled tags.
     * 
     * ref-prefix <prefix>
     * When specified, only references having a prefix matching one of
     * the provided prefixes are displayed. Multiple instances may be
     * given, in which case references matching any prefix will be
     * shown. Note that this is purely for optimization; a server MAY
     * show refs not matching the prefix if it chooses, and clients
     * should filter the result themselves.
     * 
     * unborn
     * The server will send information about HEAD even if it is a symref
     * pointing to an unborn branch in the form "unborn HEAD symref-target:<target>".
     * 
     * @see https://git-scm.com/docs/protocol-v2#_ls_refs
     * @param array $request_bytes The parsed request data
     * @return string The response in Git protocol v2 format
     */
    public function handle_ls_refs_request($request_bytes, ResponseWriter $response) {
        $response->send_header(
            'Content-Type',
            'application/x-git-upload-pack-advertisement'
        );
        $response->send_header(
            'Cache-Control',
            'no-cache'
        );
        $response->send_header(
            'Git-Protocol',
            'version=2'
        );

        $parsed = $this->parse_message($request_bytes);
        if(!$parsed) {
            // return false;
        }
        $prefix = $parsed['arguments']['ref-prefix'][0] ?? '';    

        $refs = $this->repository->list_refs($prefix);
        $first_ref = array_key_first($refs);
        foreach ($refs as $ref_name => $ref_hash) {
            $line = $ref_hash . ' ' . $ref_name;
            if($ref_name === $first_ref) {
                $line .= "\0multi_ack thin-pack side-band side-band-64k ofs-delta shallow deepen-since deepen-not deepen-relative no-progress include-tag multi_ack_detailed allow-tip-sha1-in-want allow-reachable-sha1-in-want no-done symref=HEAD:refs/heads/trunk filter object-format=sha1 agent=git/github-395dce4f6ecf";
            }
            // Format: <hash> <refname>\n
            $response->write(
                WP_Git_Pack_Processor::encode_packet_line(
                    $line . "\n"
                )
            );
        }

        // End the response with 0000
        $response->write(WP_Git_Pack_Processor::encode_packet_line("0000"));
    }

    /**
     * Capability Advertisement
     * 
     * A server which decides to communicate (based on a request from a client) using
     * protocol version 2, notifies the client by sending a version string in its initial
     * response followed by an advertisement of its capabilities. Each capability is a key
     * with an optional value. Clients must ignore all unknown keys. Semantics of unknown 
     * values are left to the definition of each key. Some capabilities will describe
     * command which can be requested to be executed by the client.
     * 
     * capability-advertisement = protocol-version
     *      capability-list
     *      flush-pkt
     * 
     * protocol-version = PKT-LINE("version 2" LF)
     * capability-list = *capability
     * capability = PKT-LINE(key[=value] LF)
     * 
     * key = 1*(ALPHA | DIGIT | "-_")
     * value = 1*(ALPHA | DIGIT | " -_.,?\/{}[]()<>!@#$%^&*+=:;")
     * flush-pkt = PKT-LINE("0000" LF)
     * 
     * @see https://git-scm.com/docs/protocol-v2#_capability_advertisement
     * @return string The capability advertisement in Git protocol v2 format
     */
    public function capability_advertise() {
        return "version 2\n" .
            "agent=git/2.37.3\n" .
            "0000";
    }

    public function parse_message($request_bytes_bytes) {
        $offset = 0;
        return [
            'capabilities' => $this->parse_capabilities($request_bytes_bytes, $offset),
            'arguments' => $this->parse_arguments($request_bytes_bytes, $offset),
        ];
    }
    
    private function parse_capabilities($request_bytes_bytes, &$offset=0) {
        $capabilities = [];
        while (true) {
            $line = WP_Git_Pack_Processor::decode_next_packet_line($request_bytes_bytes, $offset);
            if ($line === false || $line['type'] !== '#packet') {
                break;
            }
            list($key, $value) = explode('=', $line['payload']);
            $capabilities[$key] = $value;
        }
        return $capabilities;
    }

    private function parse_arguments($request_bytes_bytes, &$offset=0) {
        $arguments = [];
        while (true) {
            $line = WP_Git_Pack_Processor::decode_next_packet_line($request_bytes_bytes, $offset);
            if ($line === false || $line['type'] !== '#packet') {
                break;
            }

            $space_at = strpos($line['payload'], ' ');
            if($space_at === false) {
                $key = $line['payload'];
                $value = true;
            } else {
                $key = substr($line['payload'], 0, $space_at);
                $value = substr($line['payload'], $space_at + 1);
            }

            if(!array_key_exists($key, $arguments)) {
                $arguments[$key] = [];
            }
            $arguments[$key][] = $value;
        }
        return $arguments;
    }

    /**
     * Handle Git protocol v2 fetch command with "want" packets
     * 
     * @param array $request_bytes The parsed request data
     * @return string The response in Git protocol v2 format containing the pack data
     */
    public function handle_fetch_request($request_bytes, $response) {
        $parsed = $this->parse_message($request_bytes);
        if (!$parsed || empty($parsed['arguments']['want'])) {
            return false;
        }

        $filter_raw = $parsed['arguments']['filter'][0] ?? null;
        $filter = $this->parse_filter($filter_raw);
        if($filter === false) {
            throw new Exception('Invalid filter: ' . $filter_raw);
        }

        $have_oids = [
            WP_Git_Repository::NULL_OID => true,
        ];
        if(isset($parsed['arguments']['have'])) {
            foreach($parsed['arguments']['have'] as $have_hash) {
                $have_oids[$have_hash] = true;
            }
        }

        $objects_to_send = [];
        $acks = [];
        foreach ($parsed['arguments']['want'] as $want_hash) {
            // For all the requested non-shallow commits, find 
            // most recent parent commit the client we have in
            // common with the client.
            $common_parent_hash = WP_Git_Repository::NULL_OID;
            $commit_hash = $want_hash;
            while($this->repository->read_object($commit_hash)) {
                $objects_to_send[] = $commit_hash;
                if($this->repository->get_type() !== WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT) {
                    // Just send non-commit objects as they are. It would be lovely to
                    // delta-compress them in the future.
                    continue 2;
                }

                $parsed_commit = $this->repository->get_parsed_commit();
                if(!isset($parsed_commit['parent'])) {
                    break;
                }

                $commit_hash = $parsed_commit['parent'];
                if(isset($have_oids[$commit_hash])) {
                    break;
                }
            }
            $common_parent_hash = $commit_hash;

            // For each wanted commit, find objects not present in any of the have commits
            $new_objects = $this->repository->find_objects_added_in(
                $want_hash,
                $common_parent_hash
            );
            $objects_to_send = array_merge(
                $objects_to_send,
                iterator_to_array($new_objects)
            );
            if($common_parent_hash !== WP_Git_Repository::NULL_OID) {
                $acks[] = $common_parent_hash;
            }
        }
        $acks = array_unique($acks);
        if(count($parsed['arguments']['have']) > 0) {
            $response->write(WP_Git_Pack_Processor::encode_packet_line("acknowledgments\n"));
            if(count($acks) > 0) {
                foreach($acks as $ack) {
                    $response->write(WP_Git_Pack_Processor::encode_packet_line("ACK $ack\n"));
                }
            } else {
                $response->write(WP_Git_Pack_Processor::encode_packet_line("NAK\n"));
            }
            $response->write(WP_Git_Pack_Processor::encode_packet_line("ready\n"));
            $response->write(WP_Git_Pack_Processor::encode_packet_line("0001"));
        }
        $response->write(WP_Git_Pack_Processor::encode_packet_line("packfile\n"));

        // Pack the objects
        $objects_to_send = array_unique($objects_to_send);
        $pack_objects = [];
        foreach ($objects_to_send as $oid) {
            $this->repository->read_object($oid);
            
            // Apply blob filters if specified
            if ($this->repository->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_BLOB) {
                if ($filter['type'] === 'blob') {
                    if ($filter['filter'] === 'none') {
                        continue; // Skip all blobs
                    } else if ($filter['filter'] === 'limit') {
                        $content = $this->repository->read_entire_object_contents();
                        if (strlen($content) > $filter['size']) {
                            continue; // Skip large blobs
                        }
                    }
                }
            }

            $pack_objects[] = [
                'type' => $this->repository->get_type(),
                'content' => $this->repository->read_entire_object_contents(),
            ];
        }

        // Handle deepen if specified
        if (isset($parsed['arguments']['deepen'])) {
            // @TODO: Implement history truncation based on deepen value
            // This would involve walking the commit history and including
            // only commits within the specified depth
            throw new Exception('Deepen is not implemented yet');
        }

        // Encode the pack
        // @TODO: Stream the pack data instead of buffering it
        $pack_data = WP_Git_Pack_Processor::encode($pack_objects);

        $response->write(WP_Git_Pack_Processor::encode_packet_line("\x01" . $pack_data));
        $response->write(WP_Git_Pack_Processor::encode_packet_line("0000"));
        $response->end();
        return true;
    }

    private function parse_filter($filter) {
        if($filter === null) {
            return ['type' => 'none'];
        } else if($filter === 'blob:none') {
            return ['type' => 'blob', 'filter' => 'none'];
        } else if(str_starts_with($filter, 'blob:limit=')) {
            $limit = substr($filter, strlen('blob:limit='));
            return ['type' => 'blob', 'filter' => 'limit', 'size' => intval($limit)];
        }
        return false;
    }
}

