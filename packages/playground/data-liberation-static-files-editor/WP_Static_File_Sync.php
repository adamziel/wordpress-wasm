<?php

use WordPress\Filesystem\WP_Filesystem;

class WP_Static_File_Sync
{
    /**
     * @var string The post type to sync files for.
     */
    private $post_type;

    /**
     * @var array The previous post data before it was saved.
     */
    private $previous_post;

    /**
     * @var WP_Filesystem The filesystem to manage the static files in.
     */
    private WP_Filesystem $filesystem;

    private $last_error = null;
    private $index_file_pattern;
    private $default_index_filename;

    /**
     * Initialize the file sync manager
     *
     * @param WP_Static_Page_Manager $page_manager Manager for static page files
     * @param array $options {
     *     Optional. Configuration options for the sync manager.
     *
     *     @type string $post_type The post type to sync files for..
     * }
     */
    public function __construct(
        WP_Filesystem $filesystem,
        $options = []
    ) {
        $this->filesystem = $filesystem;
        $this->post_type = $options['post_type'] ?? WP_LOCAL_FILE_POST_TYPE;
        $this->index_file_pattern = $options['index_file_pattern'] ?? '/^index\.\w+$/';
        $this->default_index_filename = $options['default_index_filename'] ?? 'index.md';
    }

    public function initialize_sync() {
        add_action('pre_post_update', [$this, 'cache_previous_post']);
        add_action('save_post', [$this, 'on_save_post'], 10, 3);
        add_action('delete_post', [$this, 'on_delete_post']);
    }

    public function deinitialize_sync() {
        // @TODO: Confirm we don't have to preserve the original $callback
        //        array for remove_action() to work.
        remove_action('pre_post_update', [$this, 'cache_previous_post']);
        remove_action('save_post', [$this, 'on_save_post'], 10, 3);
        remove_action('delete_post', [$this, 'on_delete_post']);
    }

    /**
     * Cache the post data before it gets updated
     */
    public function cache_previous_post($post_id) {
        $this->previous_post = get_post($post_id, ARRAY_A);
    }

    /**
     * Handle saving of a post or page.
     */
    public function on_save_post(int $post_id, WP_Post $post, bool $update): void
    {
        if (!$this->wordpress_ready_for_sync()) {
            return;
        }

        if (
            empty($post->ID) ||
            $post->post_status !== 'publish' ||
            $post->post_type !== $this->post_type
        ) {
            return;
        }

        try {
            // Ensure the parent directory exists.
            if($post->post_parent) {
                $parent_file_path_before = get_post_meta($post->post_parent, 'local_file_path', true);
                $parent_file_path_after = $this->ensure_is_directory_index($parent_file_path_before);
                if(false === $parent_file_path_after) {
                    $this->bail(
                        'failed_to_ensure_parent_directory_index',
                        'Failed to ensure parent directory index for post ' . $post->post_parent
                    );
                    return;
                }
                if($parent_file_path_after !== $parent_file_path_before) {
                    update_post_meta($post->post_parent, 'local_file_path', $parent_file_path_after);
                }
                $parent_dir = dirname($parent_file_path_after);
            } else {
                $parent_dir = '/';
            }
            
            // @TODO: Handle creation of a new post

            // Figure out the new local file path of the updated page.
            $has_children = !!get_posts([
                'post_type' => $this->post_type,
                'post_parent' => $post->ID,
                'numberposts' => 1,
                'fields' => 'ids'
            ]);
            $parent_changed = $post->post_parent !== $this->previous_post['post_parent'];
            $local_path_before = get_post_meta($post_id, 'local_file_path', true) ?? '';
            if($has_children && $parent_changed) {
                // Move the entire directory subtree to the new parent.
                $local_path_to_move_from = dirname($local_path_before);
                $local_path_to_move_to = $this->append_unique_suffix(
                    wp_join_paths($parent_dir, basename($local_path_to_move_from))
                );
                if(false === $this->filesystem->rename($local_path_to_move_from, $local_path_to_move_to)) {
                    $this->bail('failed_to_rename_file', 'Failed to rename file: ' . $local_path_to_move_from);
                    return;
                }
                update_post_meta($post_id, 'local_file_path', $local_path_to_move_to);
                $local_path_changed = true;
                $local_path_after = wp_join_paths($local_path_to_move_to, basename($local_path_before));
            } else {
                $filename_after = $has_children ? 'index' : sanitize_title($post->post_name);
                
                $extension = pathinfo($local_path_before, PATHINFO_EXTENSION) ?: 'md';
                if($extension) {
                    $filename_after .= '.' . $extension;
                }

                $local_path_after = wp_join_paths($parent_dir, $filename_after);
                $local_path_changed = !$local_path_before || $local_path_before !== $local_path_after;
                if($local_path_changed) {
                    $local_path_after = $this->append_unique_suffix($local_path_after);
                }

                $success = $this->filesystem->put_contents(
                    $local_path_after,
                    $this->convert_content($post_id)
                );
                if(false === $success) {
                    $this->bail('failed_to_create_file', 'Failed to create file: ' . $local_path_after);
                    return;
                }
            }
            if($local_path_changed) {
                if($local_path_before) {
                    $this->filesystem->rm($local_path_before);
                }
                update_post_meta($post_id, 'local_file_path', $local_path_after);
            }

            // If we're moving the page under a new parent, flatten the old parent's
            // directory if it now contains only the index file.
            if($parent_changed && $this->previous_post['post_parent']) {
                $old_parent_prev_path = get_post_meta($this->previous_post['post_parent'], 'local_file_path', true);
                $old_parent_new_path = $this->flatten_parent_if_needed($old_parent_prev_path);
                if($old_parent_new_path) {
                    update_post_meta($this->previous_post['post_parent'], 'local_file_path', $old_parent_new_path);
                }
            }

            // If the page we just updated was a parent node itself, update, the local_file_path
            // meta of its entire subtree.
            if($this->previous_post['post_parent']) {
                $this->update_indexed_local_file_paths_for_children($post->ID);
            }
        } catch(Exception $e) {
            // @TODO: Handle failures gracefully.
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            throw $e;
        }
    }

    private function update_indexed_local_file_paths_for_children($parent_id) {
        $children = get_posts([
            'post_type' => $this->post_type,
            'post_parent' => $parent_id,
        ]);
        if(empty($children)) {
            return;
        }
        $parent_new_file_path = get_post_meta($parent_id, 'local_file_path', true);
        foreach($children as $child) {
            $child_local_path_before = get_post_meta($child->ID, 'local_file_path', true);
            $child_local_path_after = dirname($parent_new_file_path) . '/' . basename($child_local_path_before);
            update_post_meta($child->ID, 'local_file_path', $child_local_path_after);
            $this->update_indexed_local_file_paths_for_children($child->ID);
        }
    }

    /**
     * Handle deletion of a post or page.
     */
    public function on_delete_post(int $post_id): void
    {
        if (!$this->wordpress_ready_for_sync()) {
            return;
        }
        if (!$post_id) {
            return;
        }
        $post = get_post($post_id);
        if (
            $post->post_status !== 'publish' ||
            $post->post_type !== $this->post_type
        ) {
            return;
        }

        $local_file_path = get_post_meta($post_id, 'local_file_path', true);
        if (! $local_file_path ) {
            return;
        }

        if (! $this->filesystem->exists($local_file_path)) {
            return;
        }

        $has_children = !!get_posts([
            'post_type' => $this->post_type,
            'post_parent' => $post_id,
            'numberposts' => 1,
            'fields' => 'ids'
        ]);

        if($has_children) {
            $path_to_delete = dirname($local_file_path);
            $success = $this->filesystem->rmdir($path_to_delete, ['recursive' => true]);
        } else {
            $path_to_delete = $local_file_path;
            $success = $this->filesystem->rm($path_to_delete);
        }

        if(!$success) {
            $this->bail('failed_to_delete_directory', 'Failed to delete local file: ' . $path_to_delete);
            return;
        }

        $this->flatten_parent_if_needed(dirname($path_to_delete));
    }

    private function wordpress_ready_for_sync(): bool {
        // Ignore auto-saves or revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        // Skip if in maintenance mode
        if (wp_is_maintenance_mode()) {
            return false;
        }

        if (defined('WP_IMPORTING') && WP_IMPORTING) {
            return false;
        }

        return true;
    }

    private function convert_content( $page_id ) {
        $page = get_post($page_id);
        if(!$page) {
            return '';
        }

        $content_converter = get_post_meta($page_id, 'content_converter', true) ?: 'md';
        
        /**
         * @TODO: Decide – should we only do one of the following
         *        instead of both?
         * 
         * 1. Include the title as the first H1 block
         * 2. Include the title as a metadata field
         */
        $title_block = (
            WP_Import_Utils::block_opener('heading', array('level' => 1)) . 
            '<h1>' . esc_html(get_the_title($page_id)) . '</h1>' . 
            WP_Import_Utils::block_closer('heading')
        );
        $block_markup = $title_block . $page->post_content;
        $metadata = array(
            'title' => get_the_title($page_id),
        );

        switch($content_converter) {
            // case 'blocks':
            //     $converter = new WP_Blocks_To_Blocks(
            //         $block_markup,
            //         $metadata
            //     );
            //     break;
            // case 'html':
            // case 'xhtml':
            //     $converter = new WP_Blocks_To_HTML(
            //         $block_markup,
            //         $metadata
            //     );
            //     break;
            case 'md':
            default:
                $converter = new WP_Blocks_To_Markdown(
                    $block_markup,
                    $metadata
                );
                break;
        }
        if(false === $converter->convert()) {
            // @TODO: error handling.
            return;
        }
        return $converter->get_result();
    }

    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * Ensure the given path is a directory index (e.g., becomes `/index.{extension}`).
     */
    public function ensure_is_directory_index(string $path)
    {
        // If we're given a directory, ensure it has an index file.
        if ($this->filesystem->is_dir($path)) {
            $index_file = $this->find_index_file($path);
            if (!$index_file) {
                // Default to Markdown. @TODO: Make this configurable.
                $index_file = wp_join_paths($path, $this->default_index_filename);
                $this->filesystem->put_contents($index_file, ''); // Create an empty index file
            }
            return $index_file;
        }
        
        // If we're given a file, create a parent directory with the
        // same name (without the extension) and move the file inside
        // as its index file.
        if ($this->filesystem->is_file($path)) {
            // If the file is already an index file, we're done.
            if($this->is_index_file($path)) {
                return $path;
            }

            // @TODO: Handle a file with no extension.
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            $swap_path = $path;
            if ( $extension ) {
                $new_dir = $this->remove_extension($path);
            } else {
                $new_dir = $path;
                /**
                 * When the file has no extension, $new_dir is the same as $path.
                 * We need to rename the file to a unique name to avoid collisions.
                 */
                $swap_path = $this->append_unique_suffix($path);
                if(!$this->filesystem->rename($path, $swap_path)) {
                    $this->bail('failed_to_rename_file', 'Failed to rename file: ' . $path);
                    return false;
                }
            }

            if(!$this->filesystem->mkdir($new_dir)) {
                $this->bail('failed_to_create_directory', 'Failed to create directory: ' . $new_dir);
                return false;
            }

            $new_filename = $this->remove_extension($this->default_index_filename);
            if ($extension) {
                $new_filename .= ".{$extension}";
            }

            $index_file = wp_join_paths($new_dir, $new_filename);
            if(!$this->filesystem->rename($swap_path, $index_file)) {
                $this->bail('failed_to_rename_file', 'Failed to rename file: ' . $path);
                return false;
            }
            return $index_file;
        }

        $this->bail('path_not_found', 'Path does not exist: ' . $path);
        return false;
    }

    /**
     * Flatten a parent directory if it only contains an `index` file.
     */
    public function flatten_parent_if_needed(string $directory_index_path): bool
    {
        if ($this->filesystem->is_file($directory_index_path)) {
            $parent_dir = dirname($directory_index_path);
        } else if ($this->filesystem->is_dir($directory_index_path)) {
            $parent_dir = $directory_index_path;
        } else {
            return $directory_index_path;
        }

        if(!$parent_dir || $parent_dir === '/') {
            return $directory_index_path;
        }

        $files = $this->filesystem->ls($parent_dir);
        if(count($files) === 0) {
            $this->filesystem->rmdir($parent_dir);
            return $directory_index_path;
        }

        // Can't flatten if there are more than one file in the parent directory.
        if (count($files) !== 1) {
            return $directory_index_path;
        }

        if ($this->filesystem->is_dir($directory_index_path)) {
            $directory_index_path = $directory_index_path . '/' . $files[0];
        }

        if($this->is_index_file($files[0])) {
            // If the directory index is an index file, rename it from "index"
            // to the parent directory name
            $extension = pathinfo($directory_index_path, PATHINFO_EXTENSION);
            $new_filename = basename($parent_dir);
            if ($extension) {
                $new_filename .= ".{$extension}";
            }
        } else {
            // If the directory index is not an index file, keep its name
            $new_filename = $files[0];
        }

        $new_path = $this->append_unique_suffix(
            wp_join_paths(dirname($parent_dir), $new_filename)
        );
        if (!$this->filesystem->rename($directory_index_path, $new_path)) {
            $this->bail('failed_to_rename_file', 'Failed to rename file: ' . $directory_index_path . ' to ' . $new_path);
            return false;
        }

        if (!$this->filesystem->rmdir($parent_dir)) {
            $this->bail('failed_to_delete_directory', 'Failed to delete directory: ' . $parent_dir);
            return false;
        }

        return $new_path;
    }

    /**
     * Append a unique suffix to a file path to avoid collisions.
     */
    private function append_unique_suffix(string $path): string
    {
        $dir = dirname($path);
        $filename = basename($path);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        $new_path = $path;
        $counter = 1;
        while ($this->filesystem->exists($new_path)) {
            $new_filename = $name . "-{$counter}";
            if ($extension) {
                $new_filename .= "." . $extension;
            }
            $new_path = wp_join_paths($dir, $new_filename);
            $counter++;
        }
        return $new_path;
    }

    /**
     * Find the index file in a directory.
     * 
     * @TODO: Make configurable.
     */
    private function find_index_file(string $directory): ?string
    {
        $files = $this->filesystem->ls($directory);
        foreach ($files as $file) {
            if ($this->is_index_file($file)) {
                return $file;
            }
        }
        return null;
    }

    private function is_index_file(string $path): bool
    {
        return preg_match($this->index_file_pattern, basename($path));
    }
    
    private function remove_extension(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return substr($path, 0, -strlen(".{$extension}"));
    }

    private function bail($code, $message) {
        throw new Exception("$code: $message");
        // $this->last_error = new WP_Error($code, $message);
        // return false;
    }

}
