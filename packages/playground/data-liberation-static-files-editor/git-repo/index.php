<?php
/**
 * WordPress as a git repository.
 */

require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/../../data-liberation/bootstrap.php';
require_once __DIR__ . '/../../z-data-liberation-markdown/src/bootstrap.php';

use WordPress\Filesystem\WP_Local_Filesystem;
use WordPress\AsyncHttp\ResponseWriter\StreamingResponseWriter;
use WordPress\AsyncHttp\ResponseWriter\BufferingResponseWriter;

$git_repo_path = __DIR__ . '/git-test-server-data';
if(!is_dir($git_repo_path)) {
    mkdir($git_repo_path, 0777, true);
}
$fs = new WP_Local_Filesystem($git_repo_path);
$repository = new WP_Git_Repository($fs);
$git_fs = new WP_Git_Filesystem($repository);

// Ensure the root commit exists
if(!$repository->get_ref_head('HEAD')) {
    $server = new WP_Git_Server($repository);
    $repository->set_ref_head('HEAD', 'ref: refs/heads/main');
    $repository->set_ref_head('refs/heads/main', '0000000000000000000000000000000000000000');
    $main_branch_oid = $repository->commit([
        '.gitkeep' => '!',
    ]);
}

$server = new WP_Git_Server(
    $repository,
    [
        'root' => GIT_DIRECTORY_ROOT,
    ]
);

$request_bytes = file_get_contents('php://input');
$response = new BufferingResponseWriter();

$query_string = $_SERVER['REQUEST_URI'] ?? "";
$path = substr($query_string, strlen($_SERVER['PHP_SELF']));
if($path[0] === '?') {
    $path = substr($path, 1);
    $path = preg_replace('/&(amp;)?/', '?', $path, 1);
}

// Before handling the request, commit all the pages to the git repo
$synced_post_types = [
    'page',
    'post',
    'local_file',
];
switch($path) {
    // ls refs – protocol discovery
    case '/info/refs?service=git-upload-pack':
    // ls refs or fetch – smart protocol
    case '/git-upload-pack':

        // @TODO: Don't brute-force delete everything, only the
        //        delta.
        // @TODO: Do streaming and amend the commit every few changes
        // @TODO: Use the streaming exporter instead of the ad-hoc loop below
        $diff = [
            // Delete all the pages
            'updates' => [],
        ];
        foreach($synced_post_types as $post_type) {
            $pages = get_posts([
                'post_type' => $post_type,
                'post_status' => 'publish',
            ]);
            foreach($pages as $page) {
                $file_path = $post_type . '/' . $page->post_name . '.html';
                $metadata = [];
                foreach(['post_date_gmt', 'post_title', 'menu_order'] as $key) {
                    $metadata[$key] = get_post_field($key, $page->ID);
                }
                
                $converter = new WP_Block_HTML_Serializer( $page->post_content, $metadata );
                if(false === $converter->convert()) {
                    throw new Exception('Failed to convert the post to HTML');
                }
                // @TODO: Run the Markdown or block markup exporter
                $diff['updates'][$file_path] = $converter->get_result();
            }
        }
        if(!$repository->commit($diff)) {
            throw new Exception('Failed to commit changes');
        }
        break;
}

$server->handle_request($path, $request_bytes, $response);

// @TODO: Support the use-case below in the streaming importer
// @TODO: When a page is moved, don't delete the old page and create a new one but
//        rather update the existing page.
if($path === '/git-receive-pack') {
    foreach($synced_post_types as $post_type) {
        $updated_ids = [];
        foreach($git_fs->ls($post_type) as $file_name) {
            $file_path = $post_type . '/' . $file_name;
            $converter = new WP_HTML_With_Blocks_to_Blocks( 
                $git_fs->get_contents($file_path)
            );
            if(false === $converter->convert()) {
                throw new Exception('Failed to convert the post to HTML');
            }

            $existing_posts = get_posts([
                'post_type' => $post_type,
                'meta_key' => 'local_file_path',
                'meta_value' => $file_path,
            ]);

            $filename_without_extension = pathinfo($file_name, PATHINFO_FILENAME);

            if($existing_posts) {
                $post_id = $existing_posts[0]->ID;
            } else {
                $post_id = wp_insert_post([
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'post_title' => $filename_without_extension,
                    'meta_input' => [
                        'local_file_path' => $file_path,
                    ],
                ]);
            }
            $updated_ids[] = $post_id;

            $metadata = $converter->get_all_metadata(['first_value_only' => true]);
            $updated = wp_update_post(array(
                'ID' => $post_id,
                'post_name' => $filename_without_extension,
                'post_content' => $converter->get_block_markup(),
                'post_title' => $metadata['post_title'] ?? '',
                'post_date_gmt' => $metadata['post_date_gmt'] ?? '',
                'menu_order' => $metadata['menu_order'] ?? '',
                'meta_input' => $metadata,
            ));
            if(is_wp_error($updated)) {
                throw new Exception('Failed to update post');
            }
        }

        // Delete posts that were not updated (i.e. files were deleted)
        $posts_to_delete = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => $updated_ids,
            'fields' => 'ids'
        ]);

        foreach($posts_to_delete as $post_id) {
            wp_delete_post($post_id, true);
        }
    }
}
