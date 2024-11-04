<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 */

require_once __DIR__ . '/bootstrap.php';

// echo'<plaintext>';
// print_r(glob(__DIR__ . '/tests'));
// die();
add_action('init', function() {
    $reader = new WP_Markdown_Reader(<<<MD
# Global Settings & Styles (theme.json)

WordPress 5.8 comes with [a new mechanism](https://make.wordpress.org/core/2021/06/25/introducing-theme-json-in-wordpress-5-8/) to configure the editor that enables a finer-grained control and introduces the first step in managing styles for future WordPress releases: the `theme.json` file.

## Rationale

The Block Editor API has evolved at different velocities and there are some growing pains, specially in areas that affect themes. Examples of this are: the ability to [control the editor programmatically](https://make.wordpress.org/core/2020/01/23/controlling-the-block-editor/), or [a block style system](https://github.com/WordPress/gutenberg/issues/9534) that facilitates user, theme, and core style preferences.

This describes the current efforts to consolidate the various APIs related to styles into a single point – a `theme.json` file that should be located inside the root of the theme directory.

### Settings for the block editor

Instead of the proliferation of theme support flags or alternative methods, the `theme.json` files provides a canonical way to define the settings of the block editor. These settings includes things like:

-   What customization options should be made available or hidden from the user.
-   What are the default colors, font sizes... available to the user.
-   Defines the default layout of the editor (widths and available alignments).
MD
    );
    $reader->next_entity();

    $importer = new WP_Entity_Importer();
    $importer->import_entity($reader->get_entity_type(), $reader->get_entity_data());
});

add_action('init', function() {
    return;
    // $wxr_path = __DIR__ . '/tests/fixtures/wxr-simple.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/woocommerce-demo-products.xml';
    $wxr_path = __DIR__ . '/tests/wxr/a11y-unit-test-data.xml';
    $hash = md5($wxr_path);
    if(file_exists('./.imported-' . $hash)) {
        return;
    }
    touch('./.imported-' . $hash);

    $importer = new WP_Entity_Importer();

    echo '<plaintext>';
    $reader = WP_WXR_Reader::from_stream();
    $chain = new WP_Stream_Chain([
        new WP_File_Byte_Stream($wxr_path),
        new ProcessorByteStream($reader, function(WP_Byte_Stream_State $state, WP_WXR_Reader $reader) use($importer) {
            $new_input = $state->consume_input_bytes();
            // @TODO Why would this ever be null? Don't invoke this function if there's
            //       no new input.
            if(null === $new_input) {
                return false;
            }

            $reader->append_bytes($new_input);
            // @TODO Handle at the stream chain level,
            //       errors mean we can no longer continue.
            // if($reader->get_last_error()) {
            //     var_dump($reader->get_last_error());
            //     die();
            // }
            while($reader->next_entity()) {
                $importer->import_entity($reader->get_entity_type(), $reader->get_entity_data());
            }
            return ! $reader->is_finished();
        })
    ]);
    // echo '<plaintext>';
    $chain->run_to_completion();
});
