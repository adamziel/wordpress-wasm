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
    private $write_stream;

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

    public function open_read_stream($path) {
        return $this->repo->read_by_path($path);
    }

    public function next_file_chunk() {
        return $this->repo->next_body_chunk();
    }

    public function get_file_chunk() {
        return $this->repo->get_body_chunk();
    }

    public function get_last_error() {
        // @TODO: Manage our own errors in addition to passing
        //        through the underlying repo's errors.
        return $this->repo->get_last_error();
    }

    public function close_read_stream() {
        // @TODO: Implement this
    }

    public function get_streamed_file_length() {
        return $this->repo->get_length();
    }

    public function get_contents($path) {
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
                        $this->resolve_path($new_path) => $this->get_contents($old_path),
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
        // Git doesn't support empty directories so we must create an empty file.
        return $this->commit([
            'updates' => [
                $this->resolve_path($path) . '/.gitkeep' => '',
            ],
        ]);
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

    public function open_write_stream($path) {
        if($this->write_stream) {
            _doing_it_wrong(__METHOD__, 'Cannot open a new write stream while another write stream is open.', '1.0.0');
            return false;
        }
        $temp_file = tempnam(sys_get_temp_dir(), 'git_write_stream');
        if(false === $temp_file) {
            return false;
        }
        $this->write_stream = [
            'repo_path' => $this->resolve_path($path),
            'local_path' => $temp_file,
            'fp' => fopen($temp_file, 'wb'),
        ];
        return true;
    }

    public function append_bytes($data) {
        if(!$this->write_stream) {
            return false;
        }
        fwrite($this->write_stream['fp'], $data);
        return true;
    }

    public function close_write_stream($options = []) {
        if(!$this->write_stream) {
            return false;
        }
        fclose($this->write_stream['fp']);
        $repo_path = $this->write_stream['repo_path'];
        $local_path = $this->write_stream['local_path'];
        unset($this->write_stream);
        // Flush changes to the repo
        return $this->commit([
            'updates' => [
                // @TODO: Stream instead of file_get_contents
                $repo_path => file_get_contents($local_path),
            ],
            'amend' => isset($options['amend']) && $options['amend'],
            'message' => isset($options['message']) ? $options['message'] : null,
        ]);
    }

    private function commit($options) {
        if(false === $this->repo->commit($options)) {
            return false;
        }
        /**
         * Auto push if enabled
         *
         * This is a risky, best-effort PoC for automatic synchronization
         * of changes with the remote repository. There's no conflict
         * resolution here, only force overwriting of changes both locally
         * and in the remote repository.
         *
         * Let's re-work this once the notes management prototype is more mature.
         */
        if($this->auto_push) {
            if($this->client->force_push_one_commit()) {
                return true;
            }

            // If push failed, force pull and retry
            if(false === $this->client->force_pull()) {
                // If this failed, we're out of luck
                return false;
            }

            // If pull succeeded, try committing and pushing again
            if(false === $this->repo->commit($options)) {
                return false;
            }

            if(false === $this->client->force_push_one_commit()) {
                return false;
            }
        }
        return true;
    }

}
