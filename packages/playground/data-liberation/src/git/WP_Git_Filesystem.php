<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Filesystem extends WP_Abstract_Filesystem {
    /**
     * @var WP_Git_Repository
     */
    private $repo;
    /**
     * @var string
     */
    private $root;
    private $auto_push;
    private $client;

    public function __construct(
        WP_Git_Repository $repo,
        $options = []
    ) {
        $this->repo = $repo;
        $this->root = $options['root'] ?? '/';
        $this->auto_push = $options['auto_push'] ?? false;
        $this->client = $options['client'] ?? new WP_Git_Client($repo);
    }

    public function ls($parent = '/') {
        $path = $this->resolve_path($parent);
        if(false === $this->repo->read_by_path($path)) {
            return false;
        }
        $tree = $this->repo->get_parsed_tree();
        if(!$tree) {
            return false;
        }
        return array_keys($tree);
    }

    public function is_dir($path) {
        $path = $this->resolve_path($path);
        if(false === $this->repo->read_by_path($path)) {
            return false;
        }
        return WP_Git_Pack_Processor::OBJECT_TYPE_TREE === $this->repo->get_type();
    }

    public function is_file($path) {
        $path = $this->resolve_path($path);
        if(false === $this->repo->read_by_path($path)) {
            return false;
        }
        return WP_Git_Pack_Processor::OBJECT_TYPE_BLOB === $this->repo->get_type();
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
        $path = $this->resolve_path($path);
        $this->repo->read_by_path($path);
        return $this->repo->read_entire_object_contents();
    }

    private function resolve_path($path) {
        return wp_join_paths($this->root, $path);
    }

	// These methods are not a part of the interface, but they are useful
	// for dealing with a local filesystem.

	public function rename($old_path, $new_path) {
        if($this->is_file($old_path)) {
            return $this->commit(
                [
                    'updates' => [
                        $this->resolve_path($new_path) => $this->read_file($old_path),
                    ],
                    'deletes' => [
                        $this->resolve_path($old_path),
                    ],
                ]
            );
        } else if($this->is_dir($old_path)) {
            return $this->commit(
                [
                    'renames' => [
                        $this->resolve_path($old_path) => $this->resolve_path($new_path),
                    ],
                ]
            );
        } else {
            _doing_it_wrong(__METHOD__, 'Cannot rename a non-existent file or directory ' . $old_path, '1.0.0');
            return false;
        }
	}

	public function mkdir($path) {
        // Git doesn't support empty directories, let's not do anything.
		return true;
	}

	public function rm($path) {
        if($this->is_dir($path)) {
            return false;
        }
        return $this->commit(
            [
                'deletes' => [
                    $this->resolve_path($path)
                ],
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

        return $this->commit(
            [
                'deletes' => [
                    $this->resolve_path($path)
                ],
            ]
        );
	}

	public function put_contents($path, $data) {
        return $this->commit(
            [
                'updates' => [
                    $this->resolve_path($path) => $data,
                ],
            ]
        );
	}

    private function commit($changeset, $commit_meta=[]) {
        if(false === $this->repo->commit($changeset, $commit_meta)) {
            return false;
        }
        if($this->auto_push) {
            if(false === $this->client->force_push_one_commit()) {
                return false;
            }
        }
        return true;
    }

}
