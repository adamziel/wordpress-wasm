<?php

use WordPress\AsyncHTTP\Client;
use WordPress\AsyncHTTP\Request;

class WP_Attachment_Downloader {
    private $client;
    private $fps = [];
    private $output_root;
    private $partial_files = [];
    private $output_paths = [];

    public function __construct( $output_root ) {
        $this->client = new Client();
        $this->output_root = $output_root;
    }

    public function enqueue_if_not_exists($url, $output_path = null) {
        if (null === $output_path) {
            // Use the path from the URL.
            $parsed_url = parse_url($url);
            if (false === $parsed_url) {
                return false;
            }
            $output_path = $parsed_url['path'];
        }
        $output_path = $this->output_root . '/' . ltrim($output_path, '/');
        if (file_exists($output_path)) {
            return false;
        }

        $output_dir = dirname($output_path);
        if (!file_exists($output_dir)) {
            // @TODO: think through the chmod of the created directory.
            mkdir($output_dir, 0777, true);
        }

        $protocol = parse_url($url, PHP_URL_SCHEME);
        if (null === $protocol) {
            return false;
        }

        switch($protocol) {
            case 'file':
                $local_path = parse_url($url, PHP_URL_PATH);
                if (false === $local_path) {
                    return false;
                }
                // Just copy the file over.
                // @TODO: think through the chmod of the created file.
                return copy($local_path, $output_path);
            case 'http':
            case 'https':
                $request = new Request($url);
                $this->client->enqueue($request);
                $this->output_paths[$request->id] = $output_path;
                return true;
        }
        return false;
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
                $this->partial_files[$request->id] = $this->output_paths[$request->id] . '.partial';
                $this->fps[$request->id] = fopen($this->output_paths[$request->id] . '.partial', 'wb');
                break;
            case Client::EVENT_BODY_CHUNK_AVAILABLE:
                $chunk = $this->client->get_response_body_chunk();
                fwrite($this->fps[$request->id], $chunk);
                break;
            case Client::EVENT_FAILED:
                if(isset($this->fps[$request->id])) {
                    fclose($this->fps[$request->id]);
                }
                $partial_file = $this->output_root . '/' . $this->partial_files[$request->id] . '.partial';
                if(file_exists($partial_file)) {
                    unlink($partial_file);
                }
                if(isset($this->output_paths[$request->id])) {
                    unset($this->output_paths[$request->id]);
                }
                break;
            case Client::EVENT_FINISHED:
                fclose($this->fps[$request->id]);
                rename(
                    $this->output_root . '/' . $this->output_paths[$request->id] . '.partial',
                    $this->output_root . '/' . $this->partial_files[$request->id]
                );
                unset($this->partial_files[$request->id]);
                break;
        } 

        return true;
    }

}

