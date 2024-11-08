<?php

use WordPress\AsyncHTTP\Client;
use WordPress\AsyncHTTP\Request;

class WP_Attachment_Downloader {
    private $client;
    private $fps = [];
    private $output_directory;
    private $partial_files = [];
    private $output_paths = [];

    public function __construct( $output_directory ) {
        $this->client = new Client();
        $this->output_directory = $output_directory;
    }

    public function enqueue($url, $output_path) {
        $request = new Request($url);
        $this->client->enqueue($request);
        $this->output_paths[$request->id] = $output_path;
    }

    public function queue_full() {
        return count($this->client->get_active_requests()) >= 10;
    }

    public function poll() {
        if (! $this->client->await_next_event()) {
            return false;
        }
        $event = $this->client->get_event();
        $request = $this->client->get_request();
        
        switch($event) {
            case Client::EVENT_GOT_HEADERS:
                $filename = $this->get_unique_filename($request);
                $this->partial_files[$request->id] = $filename;
                $this->fps[$request->id] = fopen($this->output_directory . '/' . $filename . '.partial', 'wb');
                break;
            case Client::EVENT_BODY_CHUNK_AVAILABLE:
                $chunk = $this->client->get_response_body_chunk();
                fwrite($this->fps[$request->id], $chunk);
                break;
            case Client::EVENT_FAILED:
                fclose($this->fps[$request->id]);
                unlink($this->output_directory . '/' . $this->partial_files[$request->id] . '.partial');
                unset($this->partial_files[$request->id]);
                break;
            case Client::EVENT_FINISHED:
                fclose($this->fps[$request->id]);
                rename(
                    $this->output_directory . '/' . $this->partial_files[$request->id] . '.partial',
                    $this->output_directory . '/' . $this->partial_files[$request->id]
                );
                unset($this->partial_files[$request->id]);
                break;
        } 

        return true;
    }

    private function filename_from_request(Request $request) {
        $url = $request->url;
        $path = parse_url($url, PHP_URL_PATH);
        $last_segment = basename($path);
        return $last_segment ?: $request->id;
    }

    private function get_unique_filename(Request $request) {
        $base_filename = $this->filename_from_request($request);
        $filename = $base_filename;
        $counter = 1;

        // Keep incrementing counter until we find a filename that doesn't exist
        while (file_exists($this->output_directory . '/' . $filename) || 
               file_exists($this->output_directory . '/' . $filename . '.partial')) {
            $info = pathinfo($base_filename);
            $filename = $info['filename'] . '-' . $counter;
            if (isset($info['extension'])) {
                $filename .= '.' . $info['extension'];
            }
            $counter++;
        }

        return $filename;
    }
}

