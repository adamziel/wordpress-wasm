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
     * @param array $request The parsed request data
     * @return string The response in Git protocol v2 format
     */
    public function handle_ls_refs_request($request) {
        $parsed = $this->parse_ls_refs_request($request);
        if(!$parsed) {
            return false;
        }
        $prefix = $parsed['arguments']['ref-prefix'] ?? '';    

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

    /**
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
     * 
     * @param string $request_bytes Raw request bytes
     * @return array Parsed request data
     */
    public function parse_ls_refs_request($request_bytes) {
        $parsed = [
            'capabilities' => [],
            'arguments' => [],
        ];

        // Parse the capability advertisement part
        $offset = 0;
        while (true) {
            $line = WP_Git_Pack_Processor::decode_next_packet_line($request_bytes, $offset);
            if ($line === false || $line['type'] !== '#packet') {
                break;
            }
            
            list($key, $value) = explode('=', $line['payload']);
            $parsed['capabilities'][$key] = $value;
        }

        // Parse the optional arguments part
        while (true) {
            $line = WP_Git_Pack_Processor::decode_next_packet_line($request_bytes, $offset);
            if ($line === false || $line['type'] !== '#packet') {
                break;
            }

            $space_at = strpos($line['payload'], ' ');
            if($space_at === false) {
                $parsed['arguments'][$line['payload']] = true;
                continue;
            }
            $key = substr($line['payload'], 0, $space_at);
            $value = substr($line['payload'], $space_at + 1);
            $parsed['arguments'][$key] = $value;
        }

        return $parsed;
    }

}
