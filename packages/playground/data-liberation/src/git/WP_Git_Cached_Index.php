<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Cached_Index {

    private $fs;

    private $oid;
    private $type;
    private $length;
    private $contents;
    private $parsed_commit;
    private $parsed_tree;
    private $last_error;

    private const DELETE_PLACEHOLDER = 'DELETE_PLACEHOLDER';

    public function __construct(
        WP_Abstract_Filesystem $fs
    ) {
        $this->fs = $fs;
        if(!$this->fs->is_dir('objects')) {
            $this->fs->mkdir('objects');
        }
        if(!$this->fs->is_dir('refs')) {
            $this->fs->mkdir('refs');
        }
        if(!$this->fs->is_dir('refs/heads')) {
            $this->fs->mkdir('refs/heads');
        }
    }

    /**
     * @TODO: Streaming read. Don't load everything into memory.
     */
    public function read_object($oid) {
        // Reset the object state
        $this->oid = null;
        $this->type = null;
        $this->length = null;
        $this->contents = null;
        $this->parsed_commit = null;
        $this->parsed_tree = null;

        $contents = $this->fs->read_file($this->get_object_path($oid));
        $contents = WP_Git_Pack_Processor::inflate($contents);
        $type_length = strpos($contents, ' ');
        $this->oid = $oid;
        $this->type = substr($contents, 0, $type_length);
        $this->length = substr($contents, $type_length + 1, strpos($contents, "\x00", $type_length) - $type_length - 1);
        $this->contents = substr($contents, strpos($contents, "\x00", $type_length) + 1);
        if($this->type === WP_Git_Pack_Processor::OBJECT_NAMES[WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT]) {
            $this->parsed_commit = WP_Git_Pack_Processor::parse_commit_message($this->contents);
        } else if($this->type === WP_Git_Pack_Processor::OBJECT_NAMES[WP_Git_Pack_Processor::OBJECT_TYPE_TREE]) {
            $this->parsed_tree = WP_Git_Pack_Processor::parse_tree_bytes($this->contents);
        }
        return true;
    }

    public function oid_exists($oid) {
        return $this->fs->is_file($this->get_object_path($oid));
    }

    public function read_by_path($path, $root_tree_oid=null) {
        if($root_tree_oid === null) {
            $head_oid = $this->get_ref_head('HEAD');
            if(false === $this->read_object($head_oid)) {
                return false;
            }
            $root_tree_oid = $this->get_commit_tree_oid();
        }
        if(false === $this->read_object($root_tree_oid)) {
            return false;
        }

        $path = trim($path, '/');
        if (empty($path)) {
            return true;
        }

        $path_segments = explode('/', $path);
        foreach ($path_segments as $segment) {
            if (!isset($this->parsed_tree[$segment])) {
                return null;
            }
            $next_oid = $this->parsed_tree[$segment]['sha1'];
            if(false === $this->read_object($next_oid)) {
                return false;
            }
        }

        return true;
    }

    public function get_descendants($tree_oid) {
        if(false === $this->read_object($tree_oid)) {
            return [];
        }
        foreach ($this->parsed_tree as $object) {
            if ($object['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY) {
                yield from $this->get_descendants($object['sha1']);
            } else {
                yield $object;
            }
        }
    }

    public function get_type() {
        return $this->type;
    }

    public function get_length() {
        return $this->length;
    }

    public function get_contents() {
        return $this->contents;
    }

    public function get_parsed_commit() {
        return $this->parsed_commit;
    }

    public function get_commit_tree_oid() {
        return $this->parsed_commit['tree'];
    }

    public function get_parsed_tree() {
        return $this->parsed_tree;
    }

    public function set_ref_head($ref, $oid) {
        if($ref !== 'HEAD' && !str_starts_with($ref, 'refs/heads/')) {
            _doing_it_wrong(__METHOD__, 'Invalid head: ' . $ref, '1.0.0');
            return false;
        }
        return $this->fs->put_contents($ref, $oid);
    }

    public function get_ref_head($ref='HEAD') {
        if($ref === 'HEAD') {
            $ref = $this->get_HEAD_ref();
        } else if(!str_starts_with($ref, 'refs/heads/')) {
            _doing_it_wrong(__METHOD__, 'Invalid head: ' . $ref, '1.0.0');
            return false;
        }
        return trim($this->fs->read_file($ref));
    }

    private function get_HEAD_ref() {
        $ref_contents = $this->fs->read_file('HEAD');
        if(strpos($ref_contents, 'ref: ') !== 0) {
            return null;
        }
        return trim(substr($ref_contents, 5));
    }

    public function add_object($type, $content) {
        $oid = sha1(self::wrap_git_object($type, $content));
        if($this->oid_exists($oid)) {
            return $oid;
        }
        $oid_path = $this->get_object_path($oid);
        $oid_dir = dirname($oid_path);
        if(!$this->fs->is_dir($oid_dir)) {
            $this->fs->mkdir($oid_dir, true);
        }
        $success = $this->fs->put_contents(
            $this->get_object_path($oid),
            WP_Git_Pack_Processor::deflate(
                self::wrap_git_object($type, $content)
            )
        );
        if(!$success) {
            return false;
        }
        return $oid;
    }

    private function get_object_path($oid) {
        return 'objects/' . $oid[0] . $oid[1] . '/' . substr($oid, 2);
    }

    static private function wrap_git_object($type, $object) {
        $length = strlen($object);
        $type_name = WP_Git_Pack_Processor::OBJECT_NAMES[$type];
        return "$type_name $length\x00" . $object;
    }

    public function commit($changeset, $commit_meta=[]) {
        $commit_meta['author'] = $commit_meta['author'] ?? 'John Doe <john@example.com>';
        $commit_meta['committer'] = $commit_meta['committer'] ?? 'John Doe <john@example.com>';
        $commit_meta['message'] = $commit_meta['message'] ?? 'Changes';

        // First process all blob updates
        $updates = $changeset['updates'] ?? [];
        $deletes = $changeset['deletes'] ?? [];
        $move_trees = $changeset['move_trees'] ?? [];

        // Track which trees need updating
        $changed_trees = [
            '/' => ['entries' => []]
        ];

        // Process blob updates
        foreach ($updates as $path => $content) {
            $blob_oid = $this->add_object(WP_Git_Pack_Processor::OBJECT_TYPE_BLOB, $content);
            $this->mark_tree_path_changed($changed_trees, dirname($path));
            $changed_trees[dirname($path)]['entries'][basename($path)] = [
                'name' => basename($path),
                'mode' => WP_Git_Pack_Processor::FILE_MODE_REGULAR_NON_EXECUTABLE,
                'sha1' => $blob_oid
            ];
        }

        // Process deletes
        foreach ($deletes as $path) {
            if (!$this->read_by_path(dirname($path))) {
                _doing_it_wrong(__METHOD__, 'File not found in HEAD: ' . $path, '1.0.0');
                return false;
            }
            $this->mark_tree_path_changed($changed_trees, dirname($path));
            $changed_trees[dirname($path)]['entries'][basename($path)] = self::DELETE_PLACEHOLDER;
        }

        // Process tree moves
        foreach ($move_trees as $old_path => $new_path) {
            if (!$this->read_by_path($old_path)) {
                _doing_it_wrong(__METHOD__, 'Path not found in HEAD: ' . $old_path, '1.0.0');
                return false;
            }
            $this->mark_tree_path_changed($changed_trees, dirname($old_path));
            $this->mark_tree_path_changed($changed_trees, dirname($new_path));
            
            $changed_trees[dirname($old_path)]['entries'][basename($old_path)] = self::DELETE_PLACEHOLDER;
            $changed_trees[dirname($new_path)]['entries'][basename($new_path)] = [
                'name' => basename($new_path),
                'mode' => WP_Git_Pack_Processor::FILE_MODE_DIRECTORY,
                'sha1' => $this->oid
            ];
        }

        // Process trees bottom-up recursively
        $root_tree_oid = $this->commit_tree('/', $changed_trees);

        // Create commit object
        $commit_message = [];
        $commit_message[] = "tree " . $root_tree_oid;
        if($this->get_ref_head('HEAD')) {
            $commit_message[] = "parent " . $this->get_ref_head('HEAD');
        }
        $commit_message[] = "author " . $commit_meta['author'];
        $commit_message[] = "committer " . $commit_meta['committer'];
        $commit_message[] = "\n" . $commit_meta['message'];
        $commit_message = implode("\n", $commit_message);
        $commit_oid = $this->add_object(WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT, $commit_message);

        // Update HEAD
        $head_ref = $this->get_HEAD_ref();
        if(false === $this->set_ref_head($head_ref, $commit_oid)) {
            $this->last_error = 'Failed to set HEAD';
            return false;
        }
        return $commit_oid;
    }

    private function mark_tree_path_changed(&$changed_trees, $path) {
        while ($path !== '/') {
            if (!isset($changed_trees[$path])) {
                $changed_trees[$path] = ['entries' => []];
            }
            $path = dirname($path);
        }
    }

    private function commit_tree($path, $changed_trees) {
        $tree_objects = [];
        
        // Load existing tree if it exists
        if ($this->read_by_path($path)) {
            $tree_objects = $this->get_parsed_tree();
        }

        // Apply any changes to this tree
        if (isset($changed_trees[$path]['entries'])) {
            foreach ($changed_trees[$path]['entries'] as $name => $entry) {
                if ($entry === self::DELETE_PLACEHOLDER) {
                    unset($tree_objects[$name]);
                } else {
                    $tree_objects[$name] = $entry;
                }
            }
        }

        // Recursively process child trees
        foreach ($changed_trees as $child_path => $child_tree) {
            if (dirname($child_path) === $path && $child_path !== '/') {
                $child_oid = $this->commit_tree($child_path, $changed_trees);
                $tree_objects[basename($child_path)] = [
                    'name' => basename($child_path),
                    'mode' => WP_Git_Pack_Processor::FILE_MODE_DIRECTORY,
                    'sha1' => $child_oid
                ];
            }
        }

        // Create new tree object
        return $this->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_TREE,
            WP_Git_Pack_Processor::encode_tree_bytes($tree_objects)
        );
    }

}
