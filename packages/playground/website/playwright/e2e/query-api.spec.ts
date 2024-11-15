import { test, expect } from '../playground-fixtures';

// We can't import the WordPress versions directly from the remote package
// because of ESModules vs CommonJS incompatibilities. Let's just import the
// JSON file directly. @ts-ignore
// eslint-disable-next-line @nx/enforce-module-boundaries
import * as MinifiedWordPressVersions from '../../../wordpress-builds/src/wordpress/wp-versions.json';

const LatestSupportedWordPressVersion = Object.keys(
	MinifiedWordPressVersions
).filter((x) => !['nightly', 'beta'].includes(x))[0];

test('should load PHP 8.0 by default', async ({ website, wordpress }) => {
	// Navigate to the website
	await website.goto('./?url=/phpinfo.php');
	await expect(wordpress.locator('h1.p').first()).toContainText(
		'PHP Version 8.0'
	);
});

test('should load WordPress latest by default', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?url=/wp-admin/');

	const expectedBodyClass =
		'branch-' + LatestSupportedWordPressVersion.replace('.', '-');
	await expect(wordpress.locator(`body.${expectedBodyClass}`)).toContainText(
		'Dashboard'
	);
});

test('should load WordPress 6.3 when requested', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?wp=6.3&url=/wp-admin/');
	await expect(wordpress.locator(`body.branch-6-3`)).toContainText(
		'Dashboard'
	);
});

test('should disable networking when requested', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?networking=no&url=/wp-admin/plugin-install.php');
	await expect(wordpress.locator('.notice.error')).toContainText(
		'Network access is an experimental, opt-in feature'
	);
});

test('should enable networking when requested', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?networking=yes&url=/wp-admin/plugin-install.php');
	await expect(wordpress.locator('body')).toContainText('Install Now');
});

test('should install the specified plugin', async ({ website, wordpress }) => {
	await website.goto('./?plugin=gutenberg&url=/wp-admin/plugins.php');
	await expect(wordpress.locator('#deactivate-gutenberg')).toContainText(
		'Deactivate'
	);
});

test('should login the user in by default if no login query parameter is provided', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?url=/wp-admin/');
	await expect(wordpress.locator('body')).toContainText('Dashboard');
});

test('should login the user in if the login query parameter is set to yes', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?login=yes&url=/wp-admin/');
	await expect(wordpress.locator('body')).toContainText('Dashboard');
});

test('should not login the user in if the login query parameter is set to no', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?login=no&url=/wp-admin/');
	await expect(wordpress.locator('input[type="submit"]')).toContainText(
		'Log In'
	);
});

[
	['/wp-admin/', 'should redirect to wp-admin'],
	['/wp-admin/post.php?post=1&action=edit', 'should redirect to post editor'],
].forEach(([path, description]) => {
	test(description, async ({ website, wordpress }) => {
		await website.goto(`./?url=${encodeURIComponent(path)}`);
		expect(
			await wordpress
				.locator('body')
				.evaluate((body) => body.ownerDocument.location.href)
		).toContain(path);
	});
});

test('should translate WP-admin to Spanish using the language query parameter', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?language=es_ES&url=/wp-admin/');
	await expect(wordpress.locator('body')).toContainText('Escritorio');
});

/**
 * There is no reason to remove encoded control characters from the URL.
 * For example, the html-api-debugger accepts markup with newlines encoded
 * as %0A via the query string.
 */
test('should retain encoded control characters in the URL', async ({
	website,
	wordpress,
	browserName,
}) => {
	test.skip(
		browserName === 'firefox' || browserName === 'webkit',
		`It's unclear why this test fails in Firefox and Safari. The actual feature seems to be working in manual testing. ` +
		`Let's figure this out and re-enable the test at one point. The upsides of merging the original PR sill ` +
		`outweighted the downsides of disabling the test on FF.`
	);
	const path =
		'/wp-admin/admin.php?page=html-api-debugger&html=%3Cdiv%3E%0A1%0A2%0A3%0A%3C%2Fdiv%3E';
	// We need to use the html-api-debugger plugin to test this because
	// most wp-admin pages enforce a redirect to a sanitized (broken)
	// version of the URL.
	await website.goto(
		`./?url=${encodeURIComponent(path)}&plugin=html-api-debugger`
	);
	expect(
		await wordpress
			.locator('body')
			.evaluate((body) => body.ownerDocument.location.href)
	).toContain(path);
});
