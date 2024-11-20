<?php

/**
 * Filters the list of allowed block types in the block editor.
 *
 * This function restricts blueprint steps to only be available for the 'blueprint' post type.
 * Core blocks are allowed for all post types except blueprint.
 *
 * @param array|bool $allowed_block_types Array of block type slugs, or boolean to enable/disable all.
 * @param object     $block_editor_context The current block editor context.
 *
 * @return array The array of allowed block types.
 */
function filter_allowed_block_types($allowed_block_types, $block_editor_context)
{
    // Get all registered block types
    $all_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

    $blueprint_steps = [
        'playground-step/login',
        'playground-step/install-plugin',
        'playground-step/install-theme',
        'playground-step/enable-multisite',
        'playground-step/define-site-url',
        'playground-step/copy',
        'playground-step/activate-theme',
        'playground-step/activate-plugin',
        'playground-step/import-wordpress-files',
        'playground-step/remove-dir',
        'playground-step/remove-file',
        'playground-step/reset-data',
        'playground-step/write-file',
        'playground-step/move',
        'playground-step/define-wp-config-consts',
        'playground-step/wp-cli',
        'playground-step/run-php',
        'playground-step/unzip',
        'playground-step/update-user-meta',
        'playground-step/set-site-options',
        'playground-step/make-dir',
        'playground-step/import-wxr',
        'playground-step/set-site-language',
        'playground-step/import-theme-starter-content',
    ];

    if ($block_editor_context->post->post_type === 'blueprint') {
        // Allow only blueprint steps for 'blueprint' post type
        return $blueprint_steps;
    }

    // Allow all blocks except blueprint steps for other post types
    $allowed_block_types = array_keys($all_blocks);

    // Create a new array for the allowed blocks.
    $filtered_blocks = [];

    // Loop through each block in the allowed blocks list.
    foreach ($allowed_block_types as $block) {

        // Check if the block is not in the disallowed blocks list.
        if (! in_array($block, $blueprint_steps, true)) {

            // If it's not disallowed, add it to the filtered list.
            $filtered_blocks[] = $block;
        }
    }

    // Return the filtered list of allowed blocks
    return $filtered_blocks;
}
add_filter('allowed_block_types_all', 'filter_allowed_block_types', 10000, 2);

/**
 * Adds a custom block category 'Steps' to the block editor.
 *
 * @param array $block_categories Array of block categories.
 * @param WP_Block_Editor_Context $block_editor_context The current block editor context.
 *
 * @return array Modified array of block categories with the 'Steps' category added.
 */
function add_new_block_category($block_categories, $block_editor_context)
{
    $steps_categories = [
        [
            'slug'  => 'config',
            'title' => esc_html__('Config', 'text-domain')
        ],
        [
            'slug'  => 'extend',
            'title' => esc_html__('Extend', 'text-domain'),
        ],
        [
            'slug'  => 'file-system',
            'title' => esc_html__('File System', 'text-domain')
        ],
        [
            'slug'  => 'data',
            'title' => esc_html__('Data', 'text-domain')
        ],
        [
            'slug'  => 'scripts',
            'title' => esc_html__('Scripts', 'text-domain')
        ],
    ];


    foreach ($steps_categories as $step_category) {
        array_push($block_categories, $step_category);
    }

    return $block_categories;
}
add_filter('block_categories_all', 'add_new_block_category', 10, 2);

/**
 * Customizes the block editor settings for the 'blueprint' post type.
 * Disables template mode and changes the title placeholder.
 *
 * @param array $settings The block editor settings.
 *
 * @return array Modified block editor settings.
 */
function customize_editor_for_blueprint($settings)
{
    if (get_current_screen()->post_type === 'blueprint') {
        $settings['supportsTemplateMode'] = false;
        $settings['titlePlaceholder'] = "Add blueprint title";
    }

    return $settings;
}
add_filter('block_editor_settings_all', 'customize_editor_for_blueprint');
