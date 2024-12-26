<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Filesystem extends WP_Abstract_Filesystem {
    private $client;
    private $root;

    private $headRef;
    private $files_list;
    private $blobs_backfilled = false;

    public function __construct(WP_Git_Client $client, $root = '/') {
        $this->client = $client;
        $this->root = $root;
    }

    public function ls($parent = '/') {
        $parent = $this->resolve_path($parent);
        $tree = $this->get_files_list()->get_by_path($parent);
        if(!$tree) {
            return [];
        }
        return array_keys($tree['content']);
    }

    public function is_dir($path) {
        $path = $this->resolve_path($path);
        $tree = $this->get_files_list()->get_by_path($path);
        return isset($tree['type']) && $tree['type'] === WP_Git_Pack_Index::OBJECT_TYPE_TREE;
    }

    public function is_file($path) {
        $path = $this->resolve_path($path);
        // We may not have the blob object yet, but we surely have the parent
        // tree object. Instead of resolving the blob by its path, let's check
        // if the requested file is in the parent tree.
        $object = $this->get_files_list()->get_by_path(dirname($path));
        return (
            $object &&
            isset($object['type']) &&
            $object['type'] === WP_Git_Pack_Index::OBJECT_TYPE_TREE &&
            isset($object['content'][basename($path)])
        );
    }

    public function start_streaming_file($path) {
        throw new Exception('Not implemented');
    }

    public function next_file_chunk() {
        throw new Exception('Not implemented');
    }

    public function get_file_chunk() {
        throw new Exception('Not implemented');
    }

    public function get_error_message() {
        throw new Exception('Not implemented');
    }

    public function close_file_reader() {
        throw new Exception('Not implemented');
    }

    public function read_file($path) {
        $this->ensure_files_data();
        $path = $this->resolve_path($path);
        $object = $this->get_files_list()->get_by_path($path);
        if(!$object) {
            return false;
        }
        return $object['content'];
    }

    private function resolve_path($path) {
        return wp_join_paths($this->root, $path);
    }

    private function ensure_files_data() {
        if(!$this->blobs_backfilled) {
            $this->client->backfillBlobs(
                $this->get_files_list(),
                $this->root
            );
            $this->blobs_backfilled = true;
        }
    }

    private function get_files_list() {
        if(!$this->headRef) {
            $refs = $this->client->fetchRefs('HEAD');
            if(!isset($refs['HEAD'])) {
                throw new Exception('HEAD ref not found');
            }
            $this->headRef = $refs['HEAD'];
        }
        if(!$this->files_list) {
            $this->files_list = $this->client->list_objects($this->headRef);
        }
        return $this->files_list;
    }

}
