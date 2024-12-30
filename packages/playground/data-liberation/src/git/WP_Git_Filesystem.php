<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Filesystem extends WP_Abstract_Filesystem {
    private $client;
    private $root;

    private $branch_name;
    private $head_hash;
    private $index;
    private $blobs_backfilled = false;

    public function __construct(
        WP_Git_Client $client,
        $branch_name = 'main',
        $root = '/'
    ) {
        $this->client = $client;
        $this->root = $root;
        $this->branch_name = $branch_name;
    }

    public function ls($parent = '/') {
        $parent = $this->resolve_path($parent);
        $tree = $this->get_index()->get_by_path($parent);
        if(!$tree) {
            return [];
        }
        return array_keys($tree['content']);
    }

    public function is_dir($path) {
        $path = $this->resolve_path($path);
        // We may not have the blob object yet, but we surely have the parent
        // tree object. Instead of resolving the blob by its path, let's check
        // if the requested file is in the parent tree.
        $object = $this->get_index()->get_by_path(dirname($path));
        if(!$object || !isset($object['type']) || $object['type'] !== WP_Git_Pack_Processor::OBJECT_TYPE_TREE) {
            return false;
        }
        if(!isset($object['content'][basename($path)])) {
            return false;
        }
        $blob = $object['content'][basename($path)];

        return (
            isset($blob['mode']) &&
            $blob['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY
        );
    }

    public function is_file($path) {
        $path = $this->resolve_path($path);
        // We may not have the blob object yet, but we surely have the parent
        // tree object. Instead of resolving the blob by its path, let's check
        // if the requested file is in the parent tree.
        $object = $this->get_index()->get_by_path(dirname($path));
        if(!$object || !isset($object['type']) || $object['type'] !== WP_Git_Pack_Processor::OBJECT_TYPE_TREE) {
            return false;
        }
        if(!isset($object['content'][basename($path)])) {
            return false;
        }
        $blob = $object['content'][basename($path)];

        return (
            isset($blob['mode']) &&
            $blob['mode'] === WP_Git_Pack_Processor::FILE_MODE_REGULAR_NON_EXECUTABLE
        );
    }

    public function open_file_stream($path) {
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

    public function close_file_stream() {
        throw new Exception('Not implemented');
    }

    public function get_streamed_file_length() {
        throw new Exception('Not implemented');
    }

    public function read_file($path) {
        if(!$this->is_file($path)) {
            return false;
        }
        $this->ensure_files_data();
        $path = $this->resolve_path($path);
        $object = $this->get_index()->get_by_path($path);
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
                $this->get_index(),
                $this->root
            );
            $this->blobs_backfilled = true;
        }
    }

    private function get_index() {
        if(!$this->head_hash) {
            $key = 'refs/heads/' . $this->branch_name;
            $refs = $this->client->fetchRefs($key);
            if(!isset($refs[$key])) {
                throw new Exception($key . ' ref not found');
            }
            $this->head_hash = $refs[$key];
        }
        if(!$this->index) {
            $this->index = $this->client->list_objects($this->head_hash);
        }
        return $this->index;
    }

	// These methods are not a part of the interface, but they are useful
	// for dealing with a local filesystem.

	public function rename($old_path, $new_path) {
        if($this->is_dir($old_path)) {
            throw new Exception('Renaming directories is not supported yet');
        }
        if(!$this->is_file($old_path)) {
            return false;
        }
        return $this->commit_and_push(
            [
                $this->get_full_path($new_path) => $this->read_file($old_path),
            ],
            [
                $this->get_full_path($old_path),
            ]
        );
	}

	public function mkdir($path) {
        // Git doesn't support empty directories, let's not do anything.
		return true;
	}

	public function rm($path) {
        if($this->is_dir($path)) {
            return false;
        }
        return $this->commit_and_push(
            [],
            [
                $this->get_full_path($path)
            ]
        );
	}

	public function rmdir($path, $options = []) {
        if(!$this->is_dir($path)) {
            return false;
        }
        // There are no empty directories in Git. We're assuming
        // there are always files in the directory.
        if(!$options['recursive']) {
            return false;
        }

        return $this->commit_and_push(
            [],
            [
                $this->get_full_path($path)
            ]
        );
	}

	public function put_contents($path, $data) {
        return $this->commit_and_push(
            [
                $this->get_full_path($path) => $data,
            ]
        );
	}

	private function get_full_path($relative_path) {
        return ltrim(wp_join_paths($this->root, $relative_path), '/');
	}

    private function commit_and_push($updates=[], $deletes=[]) {
        $commit_data = $this->get_index()->derive_commit_pack_data(
            $updates,
            $deletes,
        );

        $result = $this->client->push($commit_data['objects'], [
            'branch_name' => $this->branch_name,
            'parent_hash' => $this->head_hash,
            'tree_hash' => $commit_data['root_tree_oid'],
        ]);

        if(!$result) {
            // @TODO: Error handling
            return false;
        }

        $this->head_hash = $result['new_head_hash'];
        // Reset the index so it's refetched the next time we need it.
        $this->index = null;

        return true;
    }

}
