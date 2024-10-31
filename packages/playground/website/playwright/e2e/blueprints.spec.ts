import { test, expect } from '../playground-fixtures';
import { Blueprint } from '@wp-playground/blueprints';
import { encodeStringAsBase64 } from '../../src/lib/base64';

test('Base64-encoded Blueprints should work', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/wp-admin/',
		steps: [{ step: 'login' }],
	};

	const encodedBlueprint = encodeStringAsBase64(JSON.stringify(blueprint));
	await website.goto(`/#${encodedBlueprint}`);
	await expect(wordpress.locator('body')).toContainText('Dashboard');
});

test('enableMultisite step should re-activate the plugins', async ({
	website,
	wordpress,
	browserName,
}) => {
	test.skip(
		browserName === 'firefox',
		`The multisite tests consistently fail in CI on Firefox. The root cause is unknown, ` +
			'but the issue does not occur in local testing or on https://playground.wordpress.net/. ' +
			'Perhaps it is related to using Firefox nightly or something highly specific to the CI runtime.'
	);
	const blueprint: Blueprint = {
		landingPage: '/wp-admin/plugins.php',
		steps: [
			{ step: 'login' },
			{
				step: 'installPlugin',
				pluginData: {
					resource: 'wordpress.org/plugins',
					slug: 'hello-dolly',
				},
				options: { activate: true },
			},
			{ step: 'enableMultisite' },
		],
	};

	const encodedBlueprint = JSON.stringify(blueprint);
	await website.goto(`./#${encodedBlueprint}`);
	await expect(wordpress.getByLabel('Deactivate Hello Dolly')).toHaveText(
		'Deactivate'
	);
});

test('enableMultisite step should enable a multisite', async ({
	website,
	wordpress,
	browserName,
}) => {
	test.skip(
		browserName === 'firefox',
		`The multisite tests consistently fail in CI on Firefox. The root cause is unknown, ` +
			'but the issue does not occur in local testing or on https://playground.wordpress.net/. ' +
			'Perhaps it is related to using Firefox nightly or something highly specific to the CI runtime.'
	);
	const blueprint: Blueprint = {
		landingPage: '/',
		steps: [{ step: 'enableMultisite' }],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText('My Sites');
});

test('should resolve nice permalinks (/%postname%/)', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/sample-page/',
		steps: [
			{
				step: 'setSiteOptions',
				options: {
					permalink_structure: '/%25postname%25/', // %25 is escaped "%"
				},
			},
			{
				step: 'runPHP',
				code: `<?php
					require '/wordpress/wp-load.php';
					$wp_rewrite->flush_rules();
				`,
			},
			{
				step: 'setSiteOptions',
				options: {
					blogname: 'test',
				},
			},
		],
	};

	await website.goto(`/#${JSON.stringify(blueprint)}`);
	const body = wordpress.locator('body');
	await expect(body).toContainText('Sample Page');
});

test('Landing page without the initial slash should work', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: 'wp-admin/plugins.php',
		login: true,
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText('Plugins');
});

test('wp-cli step should create a post', async ({ website, wordpress }) => {
	const blueprint: Blueprint = {
		landingPage: '/wp-admin/post.php',
		login: true,
		steps: [
			{
				step: 'wp-cli',
				command:
					"wp post create --post_title='Test post' --post_excerpt='Some content' --no-color",
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(
		wordpress.locator('body').locator('[aria-label="“Test post” (Edit)"]')
	).toBeVisible();
});

test('HTTPS requests via curl_exec() should work', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/curl-test.php',
		features: { networking: true },
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/curl-test.php',
				/**
				 * Dump the length of a known README.md file from the WordPress Playground repository.
				 *
				 * The URL:
				 *
				 * * Is served over HTTPS.
				 * * References a specific commit to avoid the file changing underfoot.
				 * * The server provides the CORS headers required for fetch() to work.
				 */
				data: `<?php
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/WordPress/wordpress-playground/5e5ba3e0f5b984ceadd5cbe6e661828c14621d25/README.md");
					curl_setopt($ch, CURLOPT_TCP_NODELAY, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					$result = curl_exec($ch);
					curl_close($ch);
					var_dump(
						strlen(
							$result
						)
					);
				`,
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	// The length must be 13061 bytes, otherwise something is wrong.
	await expect(wordpress.locator('body')).toContainText('int(13061)');
});

test('HTTPS requests via curl_exec() should fail when networking is disabled', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/curl-test.php',
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/curl-test.php',
				/**
				 * Dump the length of a known README.md file from the WordPress Playground repository.
				 *
				 * The URL:
				 *
				 * * Is served over HTTPS.
				 * * References a specific commit to avoid the file changing underfoot.
				 * * The server provides the CORS headers required for fetch() to work.
				 */
				data: `<?php
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/WordPress/wordpress-playground/5e5ba3e0f5b984ceadd5cbe6e661828c14621d25/README.md");
					curl_setopt($ch, CURLOPT_TCP_NODELAY, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					$result = curl_exec($ch);
					curl_close($ch);
					var_dump(
						strlen(
							$result
						)
					);
				`,
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText(
		'Call to undefined function curl_exec()'
	);
});

test('HTTPS requests via file_get_contents() should work', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/https-test.php',
		features: { networking: true },
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/https-test.php',
				/**
				 * Dump the length of a known README.md file from the WordPress Playground repository.
				 *
				 * The URL:
				 *
				 * * Is served over HTTPS.
				 * * References a specific commit to avoid the file changing underfoot.
				 * * The server provides the CORS headers required for fetch() to work.
				 */
				data: `<?php
					var_dump(
						strlen(
							file_get_contents(
								'https://raw.githubusercontent.com/WordPress/wordpress-playground/5e5ba3e0f5b984ceadd5cbe6e661828c14621d25/README.md'
							)
						)
					);
				`,
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText('int(13061)');
});

test('HTTPS requests via file_get_contents() should fail when networking is disabled', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/https-test.php',
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/https-test.php',
				/**
				 * Dump the length of a known README.md file from the WordPress Playground repository.
				 *
				 * The URL:
				 *
				 * * Is served over HTTPS.
				 * * References a specific commit to avoid the file changing underfoot.
				 * * The server provides the CORS headers required for fetch() to work.
				 */
				data: `<?php
					var_dump(
						strlen(
							file_get_contents(
								'https://raw.githubusercontent.com/WordPress/wordpress-playground/5e5ba3e0f5b984ceadd5cbe6e661828c14621d25/README.md'
							)
						)
					);
				`,
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText(
		'https:// wrapper is disabled in the server configuration'
	);
});

test('HTTPS requests via file_get_contents() to invalid URLs should fail', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/https-test.php',
		features: { networking: true },
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/https-test.php',
				/**
				 * The URL is invalid, so file_get_contents() should fail.
				 */
				data: `<?php
					var_dump(
						strlen(
							file_get_contents(
								'https://playground.internal/'
							)
						)
					);
				`,
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText(
		'file_get_contents(https://playground.internal/): Failed to open stream: HTTP request failed'
	);
});

test('HTTPS requests via file_get_contents() to CORS-disabled URLs should fail', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/https-test.php',
		features: { networking: true },
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/https-test.php',
				/**
				 * The URL is valid, but the server does not provide the CORS headers required for fetch() to work.
				 */
				data: `<?php
					var_dump(
						strlen(
							file_get_contents(
								'https://github.com/WordPress/wordpress-playground/blob/5e5ba3e0f5b984ceadd5cbe6e661828c14621d25/README.md'
							)
						)
					);
				`,
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText(
		'file_get_contents(https://github.com/WordPress/wordpress-playground/blob/5e5ba3e0f5b984ceadd5cbe6e661828c14621d25/README.md): Failed to open stream: HTTP request failed'
	);
});

test('PHP Shutdown should work', async ({ website, wordpress }) => {
	const blueprint: Blueprint = {
		landingPage: '/wp-admin/',
		features: { networking: true },
		steps: [
			{ step: 'login' },
			{
				step: 'writeFile',
				path: '/wordpress/wp-content/mu-plugins/rewrite.php',
				data: "<?php add_action( 'shutdown', function() { post_message_to_js('test'); } );",
			},
		],
	};
	await website.goto(`/#${JSON.stringify(blueprint)}`);
	await expect(wordpress.locator('body')).toContainText('Dashboard');
});

test('should login the user in by default if no login step is provided', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/wp-admin/',
	};

	const encodedBlueprint = JSON.stringify(blueprint);
	await website.goto(`./#${encodedBlueprint}`);
	await expect(wordpress.locator('body')).toContainText('Dashboard');
});

test('should login the user in if a login step is provided', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/wp-admin/',
		steps: [{ step: 'login', username: 'admin' }],
	};

	const encodedBlueprint = JSON.stringify(blueprint);
	await website.goto(`./#${encodedBlueprint}`);
	await expect(wordpress.locator('body')).toContainText('Dashboard');
});

['/wp-admin/', '/wp-admin/post.php?post=1&action=edit'].forEach((path) => {
	test(`should correctly redirect encoded wp-admin url to ${path}`, async ({
		website,
		wordpress,
	}) => {
		const blueprint: Blueprint = {
			landingPage: path,
		};
		const encodedBlueprint = JSON.stringify(blueprint);
		await website.goto(`./#${encodedBlueprint}`);
		expect(
			await wordpress.locator('body').evaluate((body) => body.baseURI)
		).toContain(path);
	});
});

test('should correctly redirect to a multisite wp-admin url', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/example/wp-admin/options-general.php',
		steps: [
			{
				step: 'enableMultisite',
			},
			{
				step: 'wp-cli',
				command: 'wp site create --slug=example',
			},
		],
	};

	const encodedBlueprint = JSON.stringify(blueprint);
	await website.goto(`./#${encodedBlueprint}`);
	await expect(wordpress.locator('body')).toContainText('General Settings');
});
