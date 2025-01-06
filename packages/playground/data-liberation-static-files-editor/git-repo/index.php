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
switch($path) {
    // ls refs – protocol discovery
    case '/info/refs?service=git-upload-pack':
    // ls refs or fetch – smart protocol
    case '/git-upload-pack':
        $post_types = [
            'page',
            'post',
            'local_file',
        ];

        // @TODO: Don't brute-force delete everything, only the
        //        delta.
        // @TODO: Do streaming and amend the commit every few changes
        // @TODO: Use the streaming exporter instead of the ad-hoc loop below
        $diff = [
            // Delete all the pages
            'updates' => [],
        ];
        foreach($post_types as $post_type) {
            $pages = get_posts([
                'post_type' => $post_type,
                'post_status' => 'publish',
            ]);
            foreach($pages as $page) {
                $file_path = $post_type . '/' . $page->post_name . '.html';
                // @TODO: Run the Markdown or block markup exporter
                $diff['updates'][$file_path] = $page->post_content;
            }
        }
        if(!$repository->commit($diff)) {
            throw new Exception('Failed to commit changes');
        }
        break;
}

$server->handle_request($path, $request_bytes, $response);

// @TODO: If we just pushed, run importer from the pushed branch
