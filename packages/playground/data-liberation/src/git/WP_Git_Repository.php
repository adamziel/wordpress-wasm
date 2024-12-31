<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Repository {

    private $fs;

    private $oid;
    private $type;
    private $content_inflate_handle;
    private $object_content_chunk;
    private $called_next_body_chunk;
    private $buffered_object_content;
    private $parsed_commit;
    private $parsed_tree;
    private $last_error;
    private $parsed_config;

    private const DELETE_PLACEHOLDER = 'DELETE_PLACEHOLDER';
    private const NULL_OID = '0000000000000000000000000000000000000000';

    public function __construct(
        WP_Abstract_Filesystem $fs
    ) {
        $this->fs = $fs;
        $this->initialize_filesystem();
    }

    private function initialize_filesystem() {
        $paths = [
            'objects',
            'refs',
            'refs/heads',
            'refs/remotes',
        ];
        foreach($paths as $path) {
            if(!$this->fs->is_dir($path)) {
                $this->fs->mkdir($path);
            }
        }
    }

    public function add_remote($name, $url) {
        $this->set_config_value(['remote', $name, 'url'], $url);
        // @TODO: support fetch option
        // $this->set_config_value(['remote', $name, 'fetch'], '+refs/heads/*:refs/remotes/' . $name . '/*');
    }

    public function get_remote($name) {
        $this->parse_config();
        $key = 'remote "' . $name . '"';
        return $this->parsed_config[$key] ?? null;
    }

    public function set_config_value($key, $value) {
        $this->parse_config();
        list($section, $key) = $this->parse_config_key($key);

        if(!isset($this->parsed_config[$section])) {
            $this->parsed_config[$section] = [];
        }
        $this->parsed_config[$section][$key] = $value;
        $this->write_config();
    }

    public function get_config_value($key) {
        $this->parse_config();
        list($section, $key) = $this->parse_config_key($key);
        return $this->parsed_config[$section][$key] ?? null;
    }

    private function parse_config_key($key) {
        if(is_string($key)) {
            $key = explode('.', $key);
        }
        $section_name = array_shift($key);
        $trailing_key = array_pop($key);
        $section_subkey = implode('.', $key);

        $section = $section_name;
        if($section_subkey) {
            $section .= ' "' . $section_subkey . '"';
        }
        return [$section, $trailing_key];
    }

    private function parse_config() {
        if(!$this->parsed_config) {
            if(!$this->fs->is_file('config')) {
                $this->parsed_config = [];
                return;
            }
            $this->parsed_config = parse_ini_string($this->fs->read_file('config'), true, INI_SCANNER_RAW);
        }
    }

    private function write_config() {
        $this->parse_config();
        $lines = [];
        foreach($this->parsed_config as $section => $key_value_pairs) {
            $lines[] = "[{$section}]";
            foreach($key_value_pairs as $key => $value) {
                $lines[] = "    {$key} = {$value}";
            }
        }
        $this->fs->put_contents('config', implode("\n", $lines));
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
        while($this->next_body_chunk()) {
            $content .= $this->get_body_chunk();
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

    public function next_body_chunk() {
        if(false === $this->fs->next_file_chunk()) {
            $this->last_error = $this->fs->get_error_message();
            return false;
        }
        $this->called_next_body_chunk = true;
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

    public function get_body_chunk() {
        return $this->object_content_chunk;
    }

    private function close_object_stream() {
        $this->fs->close_file_stream();
        $this->content_inflate_handle = null;
        return true;
    }

    public function get_parsed_commit() {
        if(null === $this->parsed_commit && $this->oid) {
            $commit_body = $this->read_entire_object_contents();
            $this->parsed_commit = WP_Git_Pack_Processor::parse_commit_body($commit_body);
            if(!$this->parsed_commit) {
                $this->last_error = 'Failed to parse commit';
                $this->parsed_commit = [];
            }
        }
        return $this->parsed_commit;
    }

    public function get_parsed_tree() {
        if(null === $this->parsed_tree && $this->oid) {
            $tree_contents = $this->read_entire_object_contents();
            $this->parsed_tree = WP_Git_Pack_Processor::parse_tree_bytes($tree_contents);
        }
        return $this->parsed_tree;
    }

    public function read_entire_object_contents() {
        // If we've advanced the stream, we can't reuse it to read the entire
        // object anymore. Let's re-initialize the stream.
        if($this->called_next_body_chunk) {
            $this->read_object($this->oid);
        }
        if(null !== $this->buffered_object_content) {
            return $this->buffered_object_content;
        }
        // Load the entire object into memory and keep the result
        // for later use. We'll likely need it again before we're
        // done with the current object.
        $this->buffered_object_content = $this->object_content_chunk;
        while($this->next_body_chunk()) {
            $this->buffered_object_content .= $this->get_body_chunk();
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
            $parsed_tree = $this->get_parsed_tree();
            if (!isset($parsed_tree[$segment])) {
                $this->reset();
                return false;
            }
            $next_oid = $parsed_tree[$segment]['sha1'];
            if(false === $this->read_object($next_oid)) {
                $this->reset();
                return false;
            }
        }

        return true;
    }

    public function get_last_error() {
        return $this->last_error;
    }

    public function find_path_descendants($path) {
        if(!$this->read_by_path($path)) {
            return [];
        }
        $stack = [$this->oid];
        $oids = [];
        while(!empty($stack)) {
            $oid = array_pop($stack);
            if(!$this->read_object($oid)) {
                return false;
            }
            if($this->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_TREE) {
                $tree = $this->get_parsed_tree();
                foreach($tree as $object) {
                    $oids[] = $object['sha1'];
                }
            } else if($this->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_BLOB) {
                $oids[] = $this->get_oid();
            }
        }
        return $oids;
    }

    public function find_objects_added_in($new_tree_oid, $old_tree_oid=null, $options=[]) {
        $old_tree_index = $options['old_tree_index'] ?? $this;
        if($old_tree_index === null) {
            $old_tree_index = $this;
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
            if(false === $old_tree_index->read_object($old_tree_oid)) {
                $this->last_error = 'Failed to read object: ' . $old_tree_oid;
                return false;
            }
            if($old_tree_index->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT) {
                $old_tree_oid = $old_tree_index->get_parsed_commit()['tree'];
            }
        }

        if($new_tree_oid === $old_tree_oid) {
            return false;
        }
        
        $stack = [[$new_tree_oid, $old_tree_oid]];
        
        while(!empty($stack)) {
            list($current_new_oid, $current_old_oid) = array_pop($stack);

            // Object is unchanged
            if($current_new_oid === $current_old_oid) {
                continue;
            }
            
            if(false === $this->read_object($current_new_oid)) {
                $this->last_error = 'Failed to read object: ' . $current_new_oid;
                return false;
            }
            if($this->get_type() === WP_Git_Pack_Processor::OBJECT_TYPE_BLOB) {
                yield $this->get_oid();
                continue;
            } else if($this->get_type() !== WP_Git_Pack_Processor::OBJECT_TYPE_TREE) {
                _doing_it_wrong(__METHOD__, 'Invalid object type in find_objects_added_in: ' . $this->get_type(), '1.0.0');
                return false;
            }

            $new_tree = $this->get_parsed_tree();
            yield $this->get_oid();
            
            $old_tree = [];
            if($current_old_oid) {
                if(false === $old_tree_index->read_object($current_old_oid)) {
                    $this->last_error = 'Failed to read object: ' . $current_old_oid;
                    return false;
                }
                $old_tree = $old_tree_index->get_parsed_tree();
            }

            foreach($new_tree as $name => $object) {
                $stack[] = [$object['sha1'], $old_tree[$name]['sha1'] ?? null];
            }
        }
    }

    public function set_ref_head($ref, $oid) {
        $path = $this->resolve_ref_file_path($ref);
        if(!$path) {
            return false;
        }
        return $this->fs->put_contents($path, $oid);
    }

    public function get_ref_head($ref='HEAD', $options=[]) {
        if($this->oid_exists($ref)) {
            return $ref;
        }
        $path = $this->resolve_ref_file_path($ref);
        if(!$path) {
            $this->last_error = 'Failed to resolve ref file path: ' . $ref;
            return false;
        }
        if(!$this->fs->is_file($path)) {
            $this->last_error = 'Ref file not found: ' . $path;
            return false;
        }
        $contents = trim($this->fs->read_file($path));
        if($options['resolve_ref'] ?? true) {
            return $this->get_ref_head($contents, $options);
        }
        return $contents;
    }

    private function resolve_ref_file_path($ref) {
        $ref = trim($ref);
        if(str_starts_with($ref, 'ref: ')) {
            $ref = trim(substr($ref, 5));
        }
        if(
            str_contains($ref, '/') &&
            !str_starts_with($ref, 'refs/heads/') && 
            !str_starts_with($ref, 'refs/remotes/')
        ) {
            _doing_it_wrong(__METHOD__, 'Invalid ref name: ' . $ref, '1.0.0');
            return false;
        }
        if(str_contains($ref, '../')) {
            _doing_it_wrong(__METHOD__, 'Invalid ref name: ' . $ref, '1.0.0');
            return false;
        }

        // Make sure all the directories leading up to the ref exist
        // @TODO: Support recursive mode in mkdir()
        $segments = explode('/', dirname($ref));
        $path = '';
        foreach($segments as $segment) {
            $path .= '/' . $segment;
            if(!$this->fs->is_dir($path)) {
                $this->fs->mkdir($path);
            }
        }
        return $ref;
    }

    public function branch_exists($ref) {
        $path = $this->resolve_ref_file_path($ref);
        if(!$path) {
            return false;
        }
        return $this->fs->is_file($path);
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

    public function commit($options=[]) {
        // First process all blob updates
        $updates = $options['updates'] ?? [];
        $deletes = $options['deletes'] ?? [];
        $move_trees = $options['move_trees'] ?? [];

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

        $is_amend = isset($options['amend']) && $options['amend'];

        // Process trees bottom-up recursively
        $root_tree_oid = $this->commit_tree('/', $changed_trees);

        // Create a new commit object
        $options['tree'] = $root_tree_oid;
        if($this->get_ref_head('HEAD')) {
            $options['parent'] = $this->get_ref_head('HEAD');
            if($is_amend && !$options['message']) {
                $this->read_object($options['parent']);
                $options['message'] = $this->get_parsed_commit()['message'];
            }
        }
        $commit_oid = $this->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT,
            $this->create_commit_string($options)
        );

        // Update HEAD
        $head_ref = $this->get_ref_head('HEAD', ['resolve_ref' => false]);
        if($this->branch_exists($head_ref)) {
            if(false === $this->set_ref_head($head_ref, $commit_oid)) {
                $this->last_error = 'Failed to set HEAD';
                return false;
            }
        }

        if(isset($options['amend']) && $options['amend'] && isset($options['parent'])) {
            $commit_oid = $this->squash($commit_oid, $options['parent']);
        }

        $this->reset();
        return $commit_oid;
    }

    public function squash($squash_into_commit_oid, $squash_until_ancestor_oid) {
        // Find the parent of the squashed range
        $this->read_object($squash_until_ancestor_oid);
        $new_base_oid = $this->get_parsed_commit()['parent'] ?? self::NULL_OID;

        // Reparent the commits from HEAD until $squash_into_commit_oid onto the parent
        // of the squashed range.
        $new_head = $this->reparent_commit_range(
            $this->get_ref_head('HEAD'),
            $squash_into_commit_oid,
            $new_base_oid
        );

        // Finally, set the HEAD of the current branch to the new squashed commit.
        $current_branch = $this->get_ref_head('HEAD', ['resolve_ref' => false]);
        $this->set_ref_head($current_branch, $new_head);
        
        return $new_head;
    }

    /**
     * This is not a rebase()! It won't replay the changes while resolving conflicts.
     * It just changes the parent of the specified commit range to $new_base_oid.
     */
    public function reparent_commit_range($head_oid, $last_ancestor_oid, $new_base_oid) {
        // @TODO: Error handling. Exceptions would make it very convenient – maybe let's
        //        use them internally?
        $commits_to_rebase = [];
        $moving_head = $head_oid;
        while($this->read_object($moving_head)) {
            $commits_to_rebase[] = $this->oid;
            if($this->oid === $last_ancestor_oid) {
                break;
            }
            $parent = $this->get_parsed_commit()['parent'] ?? self::NULL_OID;
            if(self::NULL_OID === $parent ) {
                _doing_it_wrong(
                    __METHOD__,
                    '$last_ancestor_oid must be an ancestor of $head_oid for reparenting to work, but ' . $last_ancestor_oid . ' is not an ancestor of ' . $this->oid . '.',
                    '1.0.0'
                );
                return false;
            }
            $moving_head = $parent;
        }

        // Rebase $squash_into_commit_oid and its descenrants onto the parent
        // of the squashed range.
        $new_parent_oid = $new_base_oid;
        for($i=count($commits_to_rebase)-1; $i>=0; $i--) {
            $this->read_object($commits_to_rebase[$i]);
            $parsed_old_commit = $this->get_parsed_commit();
            $new_parent_oid = $this->add_object(
                WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT,
                $this->derive_commit_string($parsed_old_commit, [
                    'parent' => $new_parent_oid,
                ])
            );
        }
        $new_head_oid = $new_parent_oid;

        return $new_head_oid;
    }

    private function derive_commit_string($parsed_commit, $updates) {
        /**
         * Keep the author and author_date as they are.
         *
         * The Git Book says:
         *
         * > You may be wondering what the difference is between author and committer. The
         * > author is the person who originally wrote the patch, whereas the committer is
         * > the person who last applied the patch. So, if you send in a patch to a project
         * > and one of the core members applies the patch, both of you get credit — you as
         * > the author and the core member as the committer
         * 
         * See http://git-scm.com/book/ch2-3.html for more information.
         */
        unset($updates['author']);
        unset($updates['author_date']);
        return $this->create_commit_string(array_merge($parsed_commit, $updates));
    }

    private function create_commit_string($options) {
        if(!isset($options['tree'])) {
            _doing_it_wrong(__METHOD__, '"tree" commit meta field is required', '1.0.0');
            return false;
        }
        if(!isset($options['author'])) {
            $options['author'] = $this->get_config_value('user.name') . ' <' . $this->get_config_value('user.email') . '>';
        }
        if(!isset($options['author_date'])) {
            $options['author_date'] = time() . ' +0000';
        }
        if(!isset($options['committer'])) {
            $options['committer'] = $this->get_config_value('user.name') . ' <' . $this->get_config_value('user.email') . '>';
        }
        if(!isset($options['committer_date'])) {
            $options['committer_date'] = time() . ' +0000';
        }
        $options['message'] = $options['message'] ?? 'Changes';
        $commit_message = [];
        $commit_message[] = "tree " . $options['tree'];
        if(isset($options['parent']) && $options['parent'] !== self::NULL_OID) {
            $commit_message[] = "parent " . $options['parent'];
        }
        $commit_message[] = "author " . $options['author'] . " " . $options['author_date'];
        $commit_message[] = "committer " . $options['committer'] . " " . $options['committer_date'];
        $commit_message[] = "\n" . $options['message'];
        return implode("\n", $commit_message);
    }

    private function reset() {
        $this->close_object_stream();
        $this->oid = null;
        $this->type = null;
        $this->parsed_commit = null;
        $this->parsed_tree = null;
        $this->called_next_body_chunk = false;
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

        // Git seems to require alphabetical order for the tree objects.
        // Or at least GitHub rejects the push if the tree objects are not sorted.
        ksort($tree_objects);

        // Create new tree object
        return $this->add_object(
            WP_Git_Pack_Processor::OBJECT_TYPE_TREE,
            WP_Git_Pack_Processor::encode_tree_bytes($tree_objects)
        );
    }


}
