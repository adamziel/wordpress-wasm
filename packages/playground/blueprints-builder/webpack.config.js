const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const { getWebpackEntryPoints } = require('@wordpress/scripts/utils/config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        ...getWebpackEntryPoints(),
        'editor': './src/editor/index.js',
        'steps/install-plugin/index': './src/steps/install-plugin/index.js',
        'steps/install-theme/index': './src/steps/install-theme/index.js',
        'steps/define-site-url/index': './src/steps/define-site-url/index.js',
        'steps/enable-multisite/index': './src/steps/enable-multisite/index.js',
        'steps/copy/index': './src/steps/copy/index.js',
        'steps/activate-theme/index': './src/steps/activate-theme/index.js',
        'steps/activate-plugin/index': './src/steps/activate-plugin/index.js',
        'steps/import-wordpress-files/index': './src/steps/import-wordpress-files/index.js',
        'steps/remove-dir/index': './src/steps/remove-dir/index.js',
        'steps/remove-file/index': './src/steps/remove-file/index.js',
        'steps/reset-data/index': './src/steps/reset-data/index.js',
        'steps/move/index': './src/steps/move/index.js',
        'steps/make-dir/index': './src/steps/make-dir/index.js',
        'steps/import-wxr/index': './src/steps/import-wxr/index.js',
        'steps/import-theme-starter-content/index': './src/steps/import-theme-starter-content/index.js',
        'steps/define-wp-config-consts/index': './src/steps/define-wp-config-consts/index.js',
        'steps/write-file/index': './src/steps/write-file/index.js',
        'steps/wp-cli/index': './src/steps/wp-cli/index.js',
        'steps/run-php/index': './src/steps/run-php/index.js',
        'steps/unzip/index': './src/steps/unzip/index.js',
        'steps/set-site-language/index': './src/steps/set-site-language/index.js',
        'steps/update-user-meta/index': './src/steps/update-user-meta/index.js',
        'steps/set-site-options/index': './src/steps/set-site-options/index.js',
        'steps/login/index': './src/steps/login/index.js'
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js'
    }
};