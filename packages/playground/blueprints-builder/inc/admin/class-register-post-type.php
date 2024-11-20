<?php

/**
 * This class defines a custom post type for creating and managing blueprint.
 *
 * @package wp-playground-blueprint-editor
 */

namespace WP\Admin\PlaygroundBlueprintEditor;

class BlueprintPostType
{
    /**
     * Construct that hooks into WordPress to initialize the post type and add custom columns.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_blueprint_post_type']);
    }

    /**
     * Registers the 'blueprint' post type with necessary arguments and labels.
     */
    public function register_blueprint_post_type()
    {
        $labels = [
            'name' => __('Blueprints'),
            'singular_name' => __('Blueprint'),
            'menu_name' => __('Blueprints'),
            'name_admin_bar' => __('Blueprint'),
            'add_new' => __('Add New Blueprint', 'blueprint'),
            'add_new_item' => __('Add New Blueprint'),
            'new_item' => __('New Blueprint'),
            'edit_item' => __('Edit Blueprint'),
            'view_item' => __('View Blueprint'),
            'all_items' => __('All Blueprints'),
            'search_items' => __('Search Blueprints'),
            'parent_item_colon' => __('Parent Blueprints:'),
            'not_found' => __('No blueprints found.'),
            'not_found_in_trash' => __('No blueprints found in Trash.')
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'blueprint'],
            'has_archive' => false,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-format-aside',
            'supports' => ['title', 'author', 'editor', 'custom-fields'],
        ];

        register_post_type('blueprint', $args);
    }
}
