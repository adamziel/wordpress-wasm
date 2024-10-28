import { test, expect } from '../playground-fixtures.ts';
import { Blueprint } from '@wp-playground/blueprints';

// We can't import the SupportedPHPVersions versions directly from the remote package
// because of ESModules vs CommonJS incompatibilities. Let's just import the
// JSON file directly. @ts-ignore
// eslint-disable-next-line @nx/enforce-module-boundaries
import { SupportedPHPVersions } from '../../../../php-wasm/universal/src/lib/supported-php-versions.ts';
// eslint-disable-next-line @nx/enforce-module-boundaries
import * as MinifiedWordPressVersions from '../../../wordpress-builds/src/wordpress/wp-versions.json';

test('should reflect the URL update from the navigation bar in the WordPress site', async ({
	website,
}) => {
	await website.goto('./?url=/wp-admin/');
	await website.ensureSiteManagerIsClosed();
	await expect(website.page.locator('input[value="/wp-admin/"]')).toHaveValue(
		'/wp-admin/'
	);
});

test('should correctly load /wp-admin without the trailing slash', async ({
	website,
}) => {
	await website.goto('./?url=/wp-admin');
	await website.ensureSiteManagerIsClosed();
	await expect(website.page.locator('input[value="/wp-admin/"]')).toHaveValue(
		'/wp-admin/'
	);
});

test('should switch between sites', async ({ website, browserName }) => {
	test.skip(
		browserName === 'webkit',
		`This test relies on OPFS which isn't available in Playwright's flavor of Safari.`
	);

	await website.goto('./');

	await website.ensureSiteManagerIsOpen();

	await expect(website.page.getByText('Save')).toBeEnabled();
	await website.page.getByText('Save').click();
	// We shouldn't need to explicitly call .waitFor(), but the test fails without it.
	// Playwright logs that something "intercepts pointer events", that's probably related.
	await website.page.getByText('Save in this browser').waitFor();
	await website.page.getByText('Save in this browser').click({ force: true });
	await expect(
		website.page.locator('[aria-current="page"]')
	).not.toContainText('Temporary Playground', {
		// Saving the site takes a while on CI
		timeout: 90000,
	});
	await expect(website.page.getByLabel('Playground title')).not.toContainText(
		'Temporary Playground'
	);

	await website.page
		.locator('button')
		.filter({ hasText: 'Temporary Playground' })
		.click();

	await expect(website.page.locator('[aria-current="page"]')).toContainText(
		'Temporary Playground'
	);
	await expect(website.page.getByLabel('Playground title')).toContainText(
		'Temporary Playground'
	);
});

test('should preserve PHP constants when saving a temporary site to OPFS', async ({
	website,
	browserName,
	wordpress,
}) => {
	test.skip(
		browserName === 'webkit',
		`This test relies on OPFS which isn't available in Playwright's flavor of Safari.`
	);

	// Start a site with a specific PHP constant.
	const blueprint: Blueprint = {
		landingPage: '/index.php',
		constants: { E2E_TEST_CONSTANT: 'E2E_TEST_VALUE' },
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/index.php',
				data: '<?php echo E2E_TEST_CONSTANT;',
			},
		],
	};
	await website.goto(`./#${JSON.stringify(blueprint)}`);

	await website.ensureSiteManagerIsOpen();

	await expect(website.page.getByText('Save')).toBeEnabled();
	await website.page.getByText('Save').click();
	// We shouldn't need to explicitly call .waitFor(), but the test fails without it.
	// Playwright logs that something "intercepts pointer events", that's probably related.
	await website.page.getByText('Save in this browser').waitFor();
	await website.page.getByText('Save in this browser').click({ force: true });
	await expect(
		website.page.locator('[aria-current="page"]')
	).not.toContainText('Temporary Playground', {
		// Saving the site takes a while on CI
		timeout: 90000,
	});
	await expect(website.page.getByLabel('Playground title')).not.toContainText(
		'Temporary Playground'
	);

	await website.page
		.locator('button')
		.filter({ hasText: 'Temporary Playground' })
		.click();

	// Switch back to the stored site and confirm the PHP constant is still present.
	await website.page
		.getByLabel('Saved Playgrounds')
		.locator('button')
		.last()
		.click();

	await expect(wordpress.locator('body')).toContainText('E2E_TEST_VALUE');
});

SupportedPHPVersions.forEach(async (version) => {
	/**
	 * WordPress 6.6 dropped support for PHP 7.0 and 7.1 and won't load on these versions.
	 * Therefore, we're skipping the test for these versions.
	 * @see https://make.wordpress.org/core/2024/04/08/dropping-support-for-php-7-1/
	 */
	if (['7.0', '7.1'].includes(version)) {
		return;
	}

	test(`should switch PHP version to ${version}`, async ({ website }) => {
		await website.goto(`./`);
		await website.ensureSiteManagerIsOpen();
		await website.page.getByLabel('PHP version').selectOption(version);
		await website.page
			.getByText('Apply Settings & Reset Playground')
			.click();
		await website.ensureSiteManagerIsClosed();
		await website.ensureSiteManagerIsOpen();

		await expect(website.page.getByLabel('PHP version')).toHaveValue(
			version
		);
	});
});

Object.keys(MinifiedWordPressVersions)
	// WordPress beta versions are not supported in the UI
	.filter((version) => !['beta', 'default'].includes(version))
	.forEach(async (version) => {
		test(`should switch WordPress version to ${version}`, async ({
			website,
		}) => {
			await website.goto('./');
			await website.ensureSiteManagerIsOpen();
			await website.page
				.getByLabel('WordPress version')
				.selectOption(version);
			await website.page
				.getByText('Apply Settings & Reset Playground')
				.click();
			await website.ensureSiteManagerIsClosed();
			await website.ensureSiteManagerIsOpen();

			await expect(
				website.page.getByLabel('WordPress version')
			).toHaveValue(version);
		});
	});

test('should display networking as inactive by default', async ({
	website,
}) => {
	await website.goto('./');
	await website.ensureSiteManagerIsOpen();
	await expect(website.page.getByLabel('Network access')).not.toBeChecked();
});

test('should display networking as active when networking is enabled', async ({
	website,
}) => {
	await website.goto('./?networking=yes');
	await website.ensureSiteManagerIsOpen();
	await expect(website.page.getByLabel('Network access')).toBeChecked();
});

test('should enable networking when requested', async ({ website }) => {
	await website.goto('./');

	await website.ensureSiteManagerIsOpen();
	await website.page.getByLabel('Network access').check();
	await website.page.getByText('Apply Settings & Reset Playground').click();
	await website.ensureSiteManagerIsClosed();
	await website.ensureSiteManagerIsOpen();

	await expect(website.page.getByLabel('Network access')).toBeChecked();
});

test('should disable networking when requested', async ({ website }) => {
	await website.goto('./?networking=yes');

	await website.ensureSiteManagerIsOpen();
	await website.page.getByLabel('Network access').uncheck();
	await website.page.getByText('Apply Settings & Reset Playground').click();
	await website.ensureSiteManagerIsClosed();
	await website.ensureSiteManagerIsOpen();

	await expect(website.page.getByLabel('Network access')).not.toBeChecked();
});

test('should display PHP output even when a fatal error is hit', async ({
	website,
	wordpress,
}) => {
	const blueprint: Blueprint = {
		landingPage: '/err.php',
		login: true,
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/err.php',
				data: "<?php throw new Exception('This is a fatal error'); \n",
			},
		],
	};
	await website.goto(`./#${JSON.stringify(blueprint)}`);

	await expect(wordpress.locator('body')).toContainText(
		'This is a fatal error'
	);
});

test('should keep query arguments when updating settings', async ({
	website,
	wordpress,
}) => {
	await website.goto('./?url=/wp-admin/&php=8.0&wp=6.6');

	expect(website.page.url()).toContain('?url=%2Fwp-admin%2F&php=8.0&wp=6.6');
	expect(
		await wordpress.locator('body').evaluate((body) => body.baseURI)
	).toMatch('/wp-admin/');

	await website.ensureSiteManagerIsOpen();
	await website.page.getByLabel('Network access').check();
	await website.page.getByText('Apply Settings & Reset Playground').click();
	await website.waitForNestedIframes();

	expect(website.page.url()).toMatch(
		'?url=%2Fwp-admin%2F&php=8.0&wp=6.6&networking=yes'
	);
	expect(
		await wordpress.locator('body').evaluate((body) => body.baseURI)
	).toMatch('/wp-admin/');
});
