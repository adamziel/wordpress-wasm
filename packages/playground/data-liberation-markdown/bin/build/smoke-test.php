<?php

require_once __DIR__ . '/../../../data-liberation/dist/data-liberation-core.phar.gz';
require_once __DIR__ . '/../../dist/data-liberation-markdown.phar';

/**
 * None of this will actually try to parse a file or import
 * any data. We're just making sure the importer can
 * be created without throwing an exception.
 */
$markdown_root = __DIR__ . '/markdown-test-data';
$c = WP_Markdown_Importer::create_for_markdown_directory(
    $markdown_root,
    array(
        'source_site_url' => 'file://' . $markdown_root,
        'local_markdown_assets_root' => $markdown_root,
        'local_markdown_assets_url_prefix' => '@site/',
    ),
    $import['cursor'] ?? null
);

echo 'Markdown importer created!';

