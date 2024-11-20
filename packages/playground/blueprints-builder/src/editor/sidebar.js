/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPostStatusInfo } from '@wordpress/editor';
import { copy, download, globe, code } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { useCopyToClipboard } from '@wordpress/compose';
import { downloadBlob } from '@wordpress/blob';
import { DataForm } from '@wordpress/dataviews';
import {
    Toolbar,
    ToolbarButton
} from '@wordpress/components';

/**
 * PHP and WordPress version options for dropdowns.
 */
const PHP_VERSIONS = [
    { label: 'Latest', value: 'latest' },
    { label: '8.3', value: '8.3' },
    { label: '8.2', value: '8.2' },
    { label: '8.1', value: '8.1' },
    { label: '8.0', value: '8.0' },
    { label: '7.4', value: '7.4' },
    { label: '7.3', value: '7.3' },
    { label: '7.2', value: '7.2' },
    { label: '7.1', value: '7.1' },
    { label: '7.0', value: '7.0' },
];

const WP_VERSIONS = [
    { label: 'Wordpress nightly', value: 'nightly' },
    { label: '6.6', value: '6.1' },
    { label: '6.5', value: '6.1' },
    { label: '6.4', value: '6.2' },
    { label: '6.3', value: '6.1' },
];

/**
 * Main component for displaying blueprint sidebar setting.
 */
function BlueprintSidebarSettings() {
    const { createNotice } = useDispatch(noticesStore);
    const { editPost } = useDispatch('core/editor');

    const blocks = useSelect((select) => select('core/block-editor').getBlocks(), []);
    const blueprint_config = useSelect((select) => {
        return select('core/editor').getEditedPostAttribute('meta')['_blueprint_config'] || {}
    });

    const playgroundBase = "https://playground.wordpress.net/#";
    const playgroundBuilderBase = "https://playground.wordpress.net/builder/builder.html#";

    const schema = {
        $schema: "https://playground.wordpress.net/blueprint-schema.json",
        landingPage: blueprint_config.landing_page,
        preferredVersions: {
            php: blueprint_config.php_version,
            wp: blueprint_config.wp_version
        },
        phpExtensionBundles: [
            blueprint_config.php_extension_bundles
        ],
        features: {
            networking: blueprint_config.networking
        },
        steps: []
    };

    // Function to prepare the schema
    const prepareSchema = () => {
        const blockAttributes = blocks.map(block => {
            delete block.attributes.metadata;
            return block.attributes;
        });
        schema.steps = [...blockAttributes];
        return JSON.stringify(schema, null, 2);
    };

    const handleDownload = () => {
        const preparedSchema = prepareSchema();
        if (preparedSchema) {
            downloadBlob('playground-blueprint.json', preparedSchema, 'application/json');
            createNotice('success', __('Blueprint downloaded generated!'), { type: 'snackbar' });
        }
    };

    const handleCopy = useCopyToClipboard(prepareSchema, () => {
        createNotice('success', __('Blueprint Schema Copied!'), { type: 'snackbar' });
    });

    const handleBlueprintConfig = (value) => {
        editPost({ meta: { _blueprint_config: { ...blueprint_config, ...value } } });
    };

    return (
        <>
            <PluginPostStatusInfo>
                <Toolbar>
                    <ToolbarButton icon={globe} label="Open in playground" href={playgroundBase + prepareSchema()} target="_blank" />
                    <ToolbarButton icon={download} label="Download JSON" onClick={handleDownload} />
                    <ToolbarButton icon={copy} label="Copy JSON" ref={handleCopy} />
                    <ToolbarButton icon={code} label="Open in Builder" href={playgroundBuilderBase + prepareSchema()} target="_blank" />
                </Toolbar>
            </PluginPostStatusInfo>
            <PluginDocumentSettingPanel name='playground-settings' title='Playground Settings'>
                <DataForm
                    data={{
                        php_version: blueprint_config.php_version,
                        wp_version: blueprint_config.wp_version,
                        landing_page: blueprint_config.landing_page,
                        php_extension_bundles: blueprint_config.php_extension_bundles,
                        networking: blueprint_config.networking
                    }}
                    fields={[
                        {
                            elements: PHP_VERSIONS,
                            id: 'php_version',
                            label: 'PHP Version',
                            type: 'text'
                        },
                        {
                            elements: WP_VERSIONS,
                            id: 'wp_version',
                            label: 'WP Version',
                            type: 'text'
                        },
                        {
                            id: 'landing_page',
                            label: 'Landing Page',
                            type: 'text'
                        },
                        {
                            id: 'php_extension_bundles',
                            label: 'PHP Extension Bundles',
                            type: 'text',
                            elements: [
                                { label: 'Kitchen Sink', value: 'kitchen-sink' },
                                { label: 'Light', value: 'light' },
                            ]
                        },
                        {
                            id: 'networking',
                            label: 'Networking',
                            type: 'text',
                            elements: [
                                { label: 'Enabled', value: true },
                                { label: 'Disabled', value: false },
                            ]
                        },
                    ]}
                    form={{
                        fields: [
                            'php_version',
                            'wp_version',
                            'landing_page',
                            'php_extension_bundles',
                            'networking'
                        ],
                    }}
                    onChange={handleBlueprintConfig}
                />
            </PluginDocumentSettingPanel >
        </>
    );
};

/**
 * Registers the 'blueprint-version-control' plugin.
 */
registerPlugin('blueprint-version-control', { render: BlueprintSidebarSettings });
