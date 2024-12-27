<?php

class WP_Git_Pack_Index {

    private $objects = [];
    private $by_oid = [];
    private $external_get_by_oid = null;

    static public function from_pack_data($pack_data) {
        return WP_Git_Pack_Processor::decode($pack_data);
    }

    public function __construct(
        $objects = [],
        $by_oid = []
    ) {
        $this->objects = $objects;
        if($by_oid === []) {
            $by_oid = [];
            foreach($objects as $k => $object) {
                $by_oid[$object['oid']] = $k;
            }
        }
        $this->by_oid = $by_oid;
    }

    public function set_external_get_by_oid($external_get_by_oid) {
        $this->external_get_by_oid = $external_get_by_oid;
    }

    public function get_by_oid($oid) {
        if(isset($this->by_oid[$oid])) {
            return $this->objects[$this->by_oid[$oid]];
        }
        if($this->external_get_by_oid) {
            $factory = $this->external_get_by_oid;
            return $factory($oid);
        }
        return null;
    }

    public function get_by_path($path, $root_tree_oid=null) {
        if($root_tree_oid === null) {
            foreach($this->objects as $object) {
                if($object['type'] === WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT) {
                    $root_tree_oid = $object['tree'];
                    break;
                }
            }
        }
        $current_tree = $this->get_by_oid($root_tree_oid);
        if (!$current_tree) {
            return null;
        }

        if($current_tree['type'] === WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT) {
            $current_tree = $this->get_by_oid($current_tree['tree']);
        }

        $path = trim($path, '/');
        if (empty($path)) {
            return $current_tree;
        }

        $path_segments = explode('/', $path);
        foreach ($path_segments as $segment) {
            if (!isset($current_tree['content'][$segment])) {
                return null;
            }
            $next_oid = $current_tree['content'][$segment]['sha1'];
            $current_tree = $this->get_by_oid($next_oid);
            if (!$current_tree) {
                return null;
            }
        }

        return $current_tree;
    }

    public function get_descendants($tree_oid) {
        $tree = $this->get_by_oid($tree_oid);
        if (!$tree || !isset($tree['content'])) {
            return [];
        }
        foreach ($tree['content'] as $name => $object) {
            if ($object['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY) {
                yield from $this->get_descendants($object['sha1']);
            } else {
                yield $object;
            }
        }
    }

    public function get_descendants_tree($tree_oid) {
        $tree = $this->get_by_oid($tree_oid);
        if (!$tree || !isset($tree['content'])) {
            return [];
        }

        $descendants = [];
        foreach ($tree['content'] as $name => $object) {
            if ($object['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY) {
                $descendants[$name] = $this->get_descendants_tree($object['sha1']);
            } else {
                $blob = $this->get_by_oid($object['sha1']);
                $descendants[$name] = isset($blob['content']) ? $blob['content'] : null;
            }
        }

        return $descendants;
    }

    private const DELETE_PLACEHOLDER = 'DELETE_PLACEHOLDER';

    /**
     * Computes Git objects needed to commit a changeset.
     * 
     * Important! A remote git repo will only accept the objects
     * produced by this method if:
     * 
     * * The paths in each tree are sorted alphabetically.
     * * There may be no duplicate blobs.
     * 
     * @param WP_Git_Pack_Processor $oldIndex The index containing existing objects
     * @param WP_Changeset $changeset The changes to commit
     * @return string The Git objects with type, content and SHA
     */
    public function derive_commit_pack_data(
        $updates = [],
        $deletes = []
    ) {
        $new_index = [];

        $new_tree = new stdClass();
        foreach ($updates as $path => $content) {
            $new_blob = WP_Git_Pack_Processor::create_object([
                'type' => WP_Git_Pack_Processor::OBJECT_TYPE_BLOB,
                'content' => $content,
            ]);
            $new_index[] = $new_blob;
            $this->set_oid($new_tree, $path, $new_blob['oid']);
        }
        
        foreach ($deletes as $path) {
            $this->set_oid($new_tree, $path, self::DELETE_PLACEHOLDER);
        }
        
        $root_tree = $this->backfill_trees($this, $new_index, $new_tree, '/');
        
        // Make $new_index unique by 'oid' column
        $seen_oids = [];
        $new_index = array_filter($new_index, function($obj) use (&$seen_oids) {
            if (isset($seen_oids[$obj['oid']])) {
                return false;
            }
            $seen_oids[$obj['oid']] = true;
            return true;
        });

        return [
            'objects' => $new_index,
            'root_tree_oid' => $root_tree['oid'],
        ];
    }

    private function backfill_trees(WP_Git_Pack_Index $current_index, &$new_index, $subtree_delta, $subtree_path = '/') {
        $subtree_path = ltrim($subtree_path, '/');
        $new_tree_content = [];

        $indexed_tree = $current_index->get_by_path($subtree_path);
        if($indexed_tree) {
            foreach($indexed_tree['content'] as $object) {
                // Backfill the unchanged objects from the currently indexed subtree.
                $name = $object['name'];
                if(!isset($subtree_delta->children[$name])) {
                    $new_tree_content[$name] = $object;
                }
            }
        }

        // Index changed and new objects in the current subtree.
        foreach($subtree_delta->children as $name => $subtree_child) {
            // Ignore any deleted objects.
            if(isset($subtree_child->oid) && $subtree_child->oid === self::DELETE_PLACEHOLDER) {
                continue;
            }

            // Index blobs
            switch($subtree_child->type) {
                case WP_Git_Pack_Processor::OBJECT_TYPE_BLOB:
                    $new_tree_content[$name] = [
                        'mode' => WP_Git_Pack_Processor::FILE_MODE_REGULAR_NON_EXECUTABLE,
                        'name' => $name,
                        'sha1' => $subtree_child->oid,
                    ];
                    break;
                case WP_Git_Pack_Processor::OBJECT_TYPE_TREE:
                    $subtree_object = $this->backfill_trees($current_index, $new_index, $subtree_child, $subtree_path . '/' . $name);
                    $new_tree_content[$name] = [
                        'mode' => WP_Git_Pack_Processor::FILE_MODE_DIRECTORY,
                        'name' => $name,
                        'sha1' => $subtree_object['oid'],
                    ];
                    break;
            }
        }

        $new_tree_object = WP_Git_Pack_Processor::create_object([
            'type' => WP_Git_Pack_Processor::OBJECT_TYPE_TREE,
            'content' => $new_tree_content,
        ]);

        $new_index[] = $new_tree_object;
        return $new_tree_object;
    }

    private function set_oid($root_tree, $path, $oid) {
        $blob = new stdClass();
        $blob->type = WP_Git_Pack_Processor::OBJECT_TYPE_BLOB;
        $blob->oid = $oid;

        $subtree_path = dirname($path);
        if($subtree_path === '.') {
            $subtree = $root_tree;
        } else {
            $subtree = $this->get_subtree($root_tree, $subtree_path);
        }
        $filename = basename($path);
        $subtree->children[$filename] = $blob;
    }

    private function get_subtree($root_tree, $path) {
        $path = trim($path, '/');
        $segments = explode('/', $path);
        $subtree = $root_tree;
        foreach ($segments as $segment) {
            if (!isset($subtree->children[$segment])) {
                $new_subtree = new stdClass();
                $new_subtree->type = WP_Git_Pack_Processor::OBJECT_TYPE_TREE;
                $new_subtree->children = [];
                $subtree->children[$segment] = $new_subtree;
            }
            $subtree = $subtree->children[$segment];
        }
        return $subtree;
    }

} 