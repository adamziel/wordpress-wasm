<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Cached_Index {

    private $fs;

    private $oid;
    private $type;
    private $content_inflate_handle;
    private $object_content_chunk;
    private $called_next_object_chunk;
    private $buffered_object_content;
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

    public function read_object($oid) {
        $this->reset();

        $object_path = $this->get_object_path($oid);
        if(!$this->fs->is_file($object_path)) {
            return false;
        }

        $this->oid = $oid;
        if(!$this->open_object_stream()) {
            return false;
        }

        // Read the object header and initialize the internal state
        // for the specific get_* methods below.
        $header = false;
        $content = '';
        while($this->next_object_chunk()) {
            $content .= $this->get_object_content_chunk();
            $null_byte_position = strpos($content, "\x00");
            if($null_byte_position === false) {
                continue;
            }
            $header = substr($content, 0, $null_byte_position);
            break;
        }

        if(false === $header) {
            $this->last_error = 'Failed to read the object header';
            return false;
        }

        $this->object_content_chunk = substr($content, strlen($header) + 1);

        // Parse the header
        $type_length = strpos($header, ' ');
        $type = substr($header, 0, $type_length);
        switch($type) {
            case WP_Git_Pack_Processor::OBJECT_NAMES[WP_Git_Pack_Processor::OBJECT_TYPE_BLOB]:
                $this->type = WP_Git_Pack_Processor::OBJECT_TYPE_BLOB;
                break;
            case WP_Git_Pack_Processor::OBJECT_NAMES[WP_Git_Pack_Processor::OBJECT_TYPE_TREE]:
                $this->type = WP_Git_Pack_Processor::OBJECT_TYPE_TREE;
                break;
            case WP_Git_Pack_Processor::OBJECT_NAMES[WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT]:
                $this->type = WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT;
                break;
            default:
                $this->last_error = 'Invalid object type: ' . $type;
                return false;
        }
        return true;
    }

    public function get_type() {
        return $this->type;
    }

    public function get_oid() {
        return $this->oid;
    }

    public function get_length() {
        return $this->fs->get_streamed_file_length();
    }

    private function open_object_stream() {
        $this->content_inflate_handle = inflate_init(ZLIB_ENCODING_DEFLATE);
        if(!$this->content_inflate_handle) {
            $this->last_error = 'Failed to initialize inflate handle';
            return false;
        }
        if(!$this->fs->open_file_stream($this->get_object_path($this->oid))) {
            return false;
        }
        return true;
    }

    public function next_object_chunk() {
        if(false === $this->fs->next_file_chunk()) {
            $this->last_error = $this->fs->get_error_message();
            return false;
        }
        $this->called_next_object_chunk = true;
        $chunk = $this->fs->get_file_chunk();
        $next_chunk = inflate_add($this->content_inflate_handle, $chunk);
        if(false === $next_chunk) {
            $this->last_error = 'Failed to inflate chunk';
            $this->close_object_stream();
            return false;
        }
        $this->object_content_chunk = $next_chunk;
        return true;
    }

    public function get_object_content_chunk() {
        return $this->object_content_chunk;
    }

    private function close_object_stream() {
        $this->fs->close_file_stream();
        $this->content_inflate_handle = null;
        return true;
    }

    public function get_parsed_commit() {
        if(null === $this->parsed_commit) {
            $commit_contents = $this->read_entire_object_contents();
            $this->parsed_commit = WP_Git_Pack_Processor::parse_commit_message($commit_contents);
        }
        return $this->parsed_commit;
    }

    public function get_parsed_tree() {
        if(null === $this->parsed_tree) {
            $tree_contents = $this->read_entire_object_contents();
            $this->parsed_tree = WP_Git_Pack_Processor::parse_tree_bytes($tree_contents);
        }
        return $this->parsed_tree;
    }

    public function read_entire_object_contents() {
        // If we've advanced the stream, we can't reuse it to read the entire
        // object anymore. Let's re-initialize the stream.
        if($this->called_next_object_chunk) {
            $this->read_object($this->oid);
        }
        if(null !== $this->buffered_object_content) {
            return $this->buffered_object_content;
        }
        // Load the entire object into memory and keep the result
        // for later use. We'll likely need it again before we're
        // done with the current object.
        $this->buffered_object_content = $this->object_content_chunk;
        while($this->next_object_chunk()) {
            $this->buffered_object_content .= $this->get_object_content_chunk();
        }
        return $this->buffered_object_content;
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
            $root_tree_oid = $this->get_parsed_commit()['tree'] ?? null;
        }
        if($root_tree_oid === null) {
            return false;
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

    public function get_last_error() {
        return $this->last_error;
    }

    public function find_objects_added_in($new_tree_oid, $old_tree_oid=null) {
        if($new_tree_oid === $old_tree_oid) {
            return false;
        }

        // Resolve the actual tree oid if $new_tree_oid is a commit
        if(false === $this->read_object($new_tree_oid)) {
            $this->last_error = 'Failed to read object: ' . $new_tree_oid;
            return false;
        }
        if($this->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT) {
            // yield the commit object itself
            $parsed_commit = $this->get_parsed_commit();
            $new_tree_oid = $parsed_commit['tree'];
            yield $this->oid;
        }

        // Resolve the actual tree oid if $old_tree_oid is a commit
        if($old_tree_oid) {
            if(false === $this->read_object($old_tree_oid)) {
                $this->last_error = 'Failed to read object: ' . $old_tree_oid;
                return false;
            }
            if($this->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT) {
                $old_tree_oid = $this->get_parsed_commit()['tree'];
            }
        }

        $stack = [[$new_tree_oid, $old_tree_oid]];
        
        while(!empty($stack)) {
            list($current_new_oid, $current_old_oid) = array_pop($stack);
            
            if(false === $this->read_object($current_new_oid)) {
                $this->last_error = 'Failed to read object: ' . $current_new_oid;
                return false;
            }
            $new_tree = $this->get_parsed_tree();
            
            $old_tree = [];
            if($current_old_oid) {
                if(false === $this->read_object($current_old_oid)) {
                    $this->last_error = 'Failed to read object: ' . $current_old_oid;
                    return false;
                }
                $old_tree = $this->get_parsed_tree();
            }

            foreach($new_tree as $name => $object) {
                // Object is new
                if(!isset($old_tree[$name])) {
                    if(false === $this->read_object($object['sha1'])) {
                        $this->last_error = 'Failed to read object: ' . $object['sha1'];
                        return false;
                    }
                    yield $object['sha1'];
                    if($object['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY) {
                        $stack[] = [$object['sha1'], null];
                    }
                    continue;
                }

                // Object is unchanged
                if($object['sha1'] === $old_tree[$name]['sha1']) {
                    continue;
                }

                if(false === $this->read_object($object['sha1'])) {
                    $this->last_error = 'Failed to read object: ' . $object['sha1'];
                    return false;
                }
                
                yield $object['sha1'];

                if($object['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY) {
                    // Object is a changed directory - add to stack for recursive processing
                    $stack[] = [$object['sha1'], $old_tree[$name]['sha1']];
                }
            }
        }
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
            $path = '/' . ltrim($path, '/');
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
            $path = '/' . ltrim($path, '/');
            if (!$this->read_by_path(dirname($path))) {
                _doing_it_wrong(__METHOD__, 'File not found in HEAD: ' . $path, '1.0.0');
                return false;
            }
            $this->mark_tree_path_changed($changed_trees, dirname($path));
            $changed_trees[dirname($path)]['entries'][basename($path)] = self::DELETE_PLACEHOLDER;
        }

        // Process tree moves
        foreach ($move_trees as $old_path => $new_path) {
            $old_path = '/' . ltrim($old_path, '/');
            $new_path = '/' . ltrim($new_path, '/');
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

        // Create a new commit object
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
        $this->reset();
        return $commit_oid;
    }

    private function reset() {
        $this->close_object_stream();
        $this->oid = null;
        $this->type = null;
        $this->parsed_commit = null;
        $this->parsed_tree = null;
        $this->called_next_object_chunk = false;
        $this->buffered_object_content = null;
        $this->object_content_chunk = null;
        $this->last_error = null;
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
