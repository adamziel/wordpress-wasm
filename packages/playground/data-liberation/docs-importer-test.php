<?php

require_once __DIR__ . '/bootstrap.php';

import_markdown_files();
function import_markdown_files() {
    // echo '<plaintext>';

    $importer = new WP_Entity_Importer();
    // Delete all posts
    $posts = get_posts([
        'post_type' => 'any',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);

    foreach($posts as $post) {
        wp_delete_post($post->ID, true);
    }
    // Import new posts
    global $wpdb;
    $max_post_id = $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
    $next_post_id = $max_post_id + 1000;
    // @TODO: Do we need to bump the autoincrement sequences after this?

    $root_dir = __DIR__ . '/../../docs/site/docs';

    $directory_indexes = [];
    foreach(wp_visit_file_tree($root_dir) as $event) {
        if($event->isExiting()) {
            // Clean up stale IDs to save some memory when processing
            // large directory trees.
            unset($directory_indexes[$event->dir->getRealPath()]);
            continue;
        }

        // Filter out irrelevant files
        $files = [];
        foreach($event->files as $file) {
            if('md' !== $file->getExtension()) {
                continue;
            }
            $files[] = $file;
        }

        // Find the directory index for the current directory
        $directory_index_idx = null;
        foreach($files as $idx => $file) {
            if(str_contains($file->getFilename(), 'index')) {
                $directory_index_idx = $idx;
                break;
            }
        }
        $parent_path = dirname($event->dir->getRealPath());
        $parent_id = $directory_indexes[$parent_path] ?? null;

        if(null !== $directory_index_idx) {
            array_splice($files, $directory_index_idx, 1)[0];
            $directory_index_id = import_markdown_file(
                $importer,
                file_get_contents($file->getRealPath()),
                $next_post_id,
                $parent_id,
                slug_to_title($file->getFilename()),
            );
        } else {
            // No directory index candidate – let's create a fake page
            // just to have something in the page tree.
            $directory_index_id = import_markdown_file(
                $importer,
                '',
                $next_post_id,
                $parent_id,
                slug_to_title($event->dir->getFilename()),
            );
        }
        $directory_indexes[$event->dir->getRealPath()] = $directory_index_id;
        ++$next_post_id;

        // Import the files – starting with the directory index file
        foreach($files as $file) {
            $title_fallback = slug_to_title($file->getFilename());
            import_markdown_file(
                $importer,
                file_get_contents($file->getRealPath()),
                $next_post_id,
                $directory_index_id,
                $title_fallback
            );
            ++$next_post_id;
        }
    }
    // die();
}

function import_markdown_file(
    $importer,
    $markdown,
    $post_id,
    $parent_id,
    $title_fallback = '',
    $title_override = '',
) {
    $converter = new WP_Markdown_To_Blocks($markdown);
    $converter->parse();
    $block_markup = $converter->get_block_markup();
    $frontmatter = $converter->get_frontmatter();

    $removed_title = remove_first_h1_block_from_block_markup($block_markup);
    if(false !== $removed_title) {
        $block_markup = $removed_title['remaining_html'];
    }

    $post_title = $title_override;
    if(!$post_title && !empty($removed_title['content'])) {
        $post_title = $removed_title['content'];
    }
    if(!$post_title && !empty($frontmatter['title'])) {
        // In WordPress Playground docs, the frontmatter title
        // is actually a worse candidate than the first H1 block
        //
        // There will, inevitably, be 10,000 ways people will want
        // to use this importer with different projects. Let's just
        // enable plugins to customize the title resolution.
        $post_title = $frontmatter['title'];
    }
    if(!$post_title) {
        $post_title = $title_fallback;
    }

    $entity_type = 'post';
    $entity_data = array(
        'post_id' => $post_id,
        'post_type' => 'page',
        'guid' => $frontmatter['slug'] ?? 'README',
        'post_title' => $post_title,
        'post_content' => $block_markup,
        'post_excerpt' => $frontmatter['description'] ?? '',
        'post_status' => 'publish',
    );

    if(!empty($frontmatter['slug'])) {
        $slug = $frontmatter['slug'];
        $last_segment = substr($slug, strrpos($slug, '/') + 1);
        $entity_data['post_name'] = $last_segment;
    }
    
    if(isset($frontmatter['sidebar_position'])) {
        $entity_data['post_order'] = $frontmatter['sidebar_position'];
    }

    if($parent_id) {
        $entity_data['post_parent'] = $parent_id;
    }

    return $importer->import_entity($entity_type, $entity_data);
}


function remove_first_h1_block_from_block_markup($html) {
    $p = WP_Modifiable_HTML_Processor::create_fragment($html);
    if(false === $p->next_tag()) {
        return false;
    }
    if($p->get_tag() !== 'H1') {
        return false;
    }
    $depth = $p->get_current_depth();
    $title = '';
    do {
        if(false === $p->next_token()) {
            break;
        }
        if($p->get_token_type() === '#text') {
            $title .= $p->get_modifiable_text() . ' ';
        }
    } while($p->get_current_depth() > $depth);

    if(!$title) {
        return false;
    }

    // Move past the closing comment
    $p->next_token();
    if($p->get_token_type() === '#text') {
        $p->next_token();
    }
    if($p->get_token_type() !== '#comment') {
        return false;
    }

    return [
        'content' => trim($title),
        'remaining_html' => substr(
            $html,
            $p->get_string_index_after_current_token()
        )
    ];
}

function slug_to_title($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/^\d+/', '', $name);
    $name = str_replace(
        array('-', '_'),
        ' ',
        $name
    );
    $name = ucwords($name);
    return $name;
}
