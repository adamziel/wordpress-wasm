<?php

require_once __DIR__ . '/bootstrap.php';

$reader = new WP_Serialized_Pages_Reader(__DIR__ . '/../../docs/site/docs');

while($reader->next_file()) {
    $file = $reader->get_file();
    if('md' !== $file->getExtension()) {
        continue;
    }

    if(
        !$reader->has_directory_index() ||
        str_contains($file->getFilename(), 'index')
    ) {
        $reader->mark_as_directory_index();
    }
    
    $markdown = file_get_contents($file->getRealPath());
    $blocks = WP_Markdown_To_Blocks::convert($markdown);
    // @TODO: Parse frontmatter at the beginning of $markdown
    $entity_type = 'post';
    $entity_data = array(
        'post_type' => 'post',
        'guid' => $reader->get_relative_path(),
        'post_parent' => $reader->get_parent_directory_index(),
        'post_title' => extract_title_from_block_markup($blocks) ?: extract_title_from_filename($file->getFilename()),
        'post_content' => $blocks,
        'post_status' => 'publish',
    );

}

function extract_title_from_block_markup($content) {
    $p = new WP_HTML_Processor($content);
    if(false === $p->next_tag('H1')) {
        return false;
    }
    $depth = $p->get_current_depth();
    $content = '';
    do {
        if(false === $p->next_token()) {
            break;
        }
        if($p->get_token_type() === '#text') {
            $content .= $p->get_modifiable_text() . ' ';
        }
    } while($p->get_current_depth() > $depth);

    return trim($content);
}

function extract_title_from_filename($filename) {
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
