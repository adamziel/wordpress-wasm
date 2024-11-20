<?php

/**
 * Plugin Name: Playground blueprint editor
 * Description: Design playground blueprints with a specialized block editor interface in WordPress.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: Lubus
 * Author URI: https://lubus.in/
 * Contributor: Lubus, https://lubus.in/
 * Text Domain: wp-playground-blueprint-editor
 * 
 * @package wp-playground-blueprint-editor
 */

namespace WP\PlaygroundBlueprintEditor;

use WP\Admin\PlaygroundBlueprintEditor\{
    BlueprintPostType,
    BlueprintSteps,
    EnqueueScripts,
    RegisterCustomMeta
};

defined('ABSPATH') || exit;

require_once 'vendor/autoload.php';
require_once 'inc/functions.php';

if (!class_exists('PlaygroundBlueprintEditor')) {

    /**
     * Playground blueprint editor Main Class
     */
    class PlaygroundBlueprintEditor
    {
        /**
         * The single instance of the class.
         *
         * @var PlaygroundBlueprintEditor
         */
        protected static $_instance = null;

        /**
         * Singleton instance method.
         *
         * @return self
         */
        public static function instance()
        {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * Constructor to initialize the plugin.
         */
        public function __construct()
        {
            $this->setup_constants();
            $this->bootstrap();
        }

        /**
         * Setup plugin constants.
         */
        private function setup_constants()
        {
            // Plugin version.
            if (!defined('BEPB_VERSION')) {
                define('BEPB_VERSION', '0.1.0');
            }

            // Plugin Root File.
            if (!defined('BEPB_PLUGIN_FILE')) {
                define('BEPB_PLUGIN_FILE', __FILE__);
            }

            // Plugin Folder Path.
            if (!defined('BEPB_PLUGIN_DIR')) {
                define('BEPB_PLUGIN_DIR', plugin_dir_path(BEPB_PLUGIN_FILE));
            }

            // Plugin Folder URL.
            if (!defined('BEPB_PLUGIN_URL')) {
                define('BEPB_PLUGIN_URL', plugin_dir_url(BEPB_PLUGIN_FILE));
            }

            // Plugin Basename aka: "block-editor-for-playground-blueprint/wp-playground-blueprint-editor.php".
            if (!defined('BEPB_PLUGIN_BASENAME')) {
                define('BEPB_PLUGIN_BASENAME', plugin_basename(BEPB_PLUGIN_FILE));
            }
        }

        /**
         * Bootstraps the plugin.
         */
        private function bootstrap()
        {
            new EnqueueScripts();
            new BlueprintPostType();
            new BlueprintSteps();
            new RegisterCustomMeta();
        }
    }
}

return PlaygroundBlueprintEditor::instance();
