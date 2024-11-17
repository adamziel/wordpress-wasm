<?php

/**
 * Streams bytes from a remote file.
 * 
 * Usage:
 * 
 * $file = new WP_Remote_File_Reader('https://example.com/file.txt');
 * while($file->next_chunk()) {
 *     var_dump($file->get_bytes());
 * }
 */
class WP_Remote_File_Reader {

    /**
     * @var WordPress\AsyncHttp\Client
     */
    private $client;
    private $url;
    private $request;
    private $current_chunk;

    public function __construct($url) {
        $this->client = new WordPress\AsyncHttp\Client();
        $this->url = $url;
    }

    public function next_chunk() {
        if(null === $this->request) {
            $this->request = new WordPress\AsyncHttp\Request(
                $this->url
            );
            if(false === $this->client->enqueue($this->request)) {
                // TODO: Think through error handling
                return false;
            }
        }

        while($this->client->await_next_event()) {
            switch($this->client->get_event()) {
                case WordPress\AsyncHttp\Client::EVENT_BODY_CHUNK_AVAILABLE:
                    $chunk = $this->client->get_response_body_chunk();
                    if(!is_string($chunk)) {
                        // TODO: Think through error handling
                        return false;
                    }
                    $this->current_chunk = $chunk;
                    return true;
                case WordPress\AsyncHttp\Client::EVENT_FAILED:
                    // TODO: Think through error handling. Errors are expected when working with
                    //       the network. Should we auto retry? Make it easy for the caller to retry?
                    //       Something else?
                    return false;
                case WordPress\AsyncHttp\Client::EVENT_FINISHED:
                    // TODO: Think through error handling
                    return false;
            }
        }
    }

    public function get_bytes() {
        return $this->current_chunk;
    }

}
