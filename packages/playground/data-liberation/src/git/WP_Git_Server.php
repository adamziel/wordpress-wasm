<?php

use WordPress\AsyncHttp\Client;
use WordPress\AsyncHttp\Request;
use WordPress\ByteReader\WP_String_Reader;

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
     * @param array $request The parsed request data
     * @return string The response in Git protocol v2 format
     */
    public function handle_ls_refs_request($request) {
        $parsed = $this->parse_message($request);
        if(!$parsed) {
            return false;
        }
        $prefix = $parsed['arguments']['ref-prefix'][0] ?? '';    

        $refs = $this->repository->list_refs($prefix);
        $response = '';
        foreach ($refs as $ref_name => $ref_hash) {
            // Format: <hash> <refname>\n
            $response .= WP_Git_Pack_Processor::encode_packet_line(
                $ref_hash . ' ' . $ref_name . "\n"
            );
        }

        // End the response with 0000
        return $response . "0000";
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

    public function parse_message($request_bytes) {
        $offset = 0;
        return [
            'capabilities' => $this->parse_capabilities($request_bytes, $offset),
            'arguments' => $this->parse_arguments($request_bytes, $offset),
        ];
    }
    
    private function parse_capabilities($request_bytes, &$offset=0) {
        $capabilities = [];
        while (true) {
            $line = WP_Git_Pack_Processor::decode_next_packet_line($request_bytes, $offset);
            if ($line === false || $line['type'] !== '#packet') {
                break;
            }
            list($key, $value) = explode('=', $line['payload']);
            $capabilities[$key] = $value;
        }
        return $capabilities;
    }

    private function parse_arguments($request_bytes, &$offset=0) {
        $arguments = [];
        while (true) {
            $line = WP_Git_Pack_Processor::decode_next_packet_line($request_bytes, $offset);
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
     * @param array $request The parsed request data
     * @return string The response in Git protocol v2 format containing the pack data
     */
    public function handle_fetch_request($request) {
        $parsed = $this->parse_message($request);
        if (!$parsed || empty($parsed['arguments']['want'])) {
            return false;
        }

        $objects_to_send = [];
        foreach ($parsed['arguments']['want'] as $want_hash) {
            // For each wanted commit, find objects not present in any of the have commits
            $new_objects = $this->repository->find_objects_added_in(
                $want_hash,
                $parsed['arguments']['have'] ?? ['0000000000000000000000000000000000000000']
            );
            $objects_to_send = array_merge($objects_to_send, $new_objects);
        }
        $objects_to_send = array_unique($objects_to_send);

        // Pack the objects
        $pack_objects = [];
        foreach ($objects_to_send as $oid) {
            $this->repository->read_object($oid);
            
            // Apply blob filters if specified
            if ($this->repository->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_BLOB) {
                $filter = $parsed['arguments']['filter'] ?? null;
                if ($filter) {
                    if ($filter['type'] === 'none') {
                        continue; // Skip all blobs
                    } else if ($filter['type'] === 'limit') {
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
        }

        // Encode the pack
        $pack_data = WP_Git_Pack_Processor::encode($pack_objects);

        // Format the response according to protocol v2
        return 
            WP_Git_Pack_Processor::encode_packet_line("packfile\n") .
            WP_Git_Pack_Processor::encode_packet_line("\x01" . $pack_data) . // side-band channel 1
            "0000";
    }
}
