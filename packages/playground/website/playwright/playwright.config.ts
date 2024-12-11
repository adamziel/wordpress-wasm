import { defineConfig, devices, PlaywrightTestConfig } from '@playwright/test';

const baseURL =
	process.env.PLAYWRIGHT_TEST_BASE_URL ||
	'http://127.0.0.1:5400/website-server/';

export const playwrightConfig: PlaywrightTestConfig = {
	testDir: './e2e',
	/* Run tests in files in parallel */
	fullyParallel: false,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !!process.env.CI,
	retries: 3,
	workers: 3,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: [['html'], ['list', { printSteps: true }]],
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* Base URL to use in actions like `await page.goto('/')`. */
		baseURL,
		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',
		actionTimeout: 120000,
		navigationTimeout: 120000,
	},

	timeout: 300000,
	expect: { timeout: 45000 },

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
				launchOptions: {
					args: ['--js-flags=--enable-experimental-webassembly-jspi'],
				},
			},
		},

		{
			name: 'firefox',
			use: { ...devices['Desktop Firefox'] },
		},

		{
			name: 'webkit',
			use: { ...devices['Desktop Safari'] },
		},

		/* Test against mobile viewports. */
		// {
		//   name: 'Mobile Chrome',
		//   use: { ...devices['Pixel 5'] },
		// },
		// {
		// 	name: 'Mobile Safari',
		// 	use: { ...devices['iPhone 12'] },
		// },

		/* Test against branded browsers. */
		// {
		//   name: 'Microsoft Edge',
		//   use: { ...devices['Desktop Edge'], channel: 'msedge' },
		// },
		// {
		//   name: 'Google Chrome',
		//   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
		// },
	],

	/* Run your local dev server before starting the tests */
	webServer: {
		command: 'npx nx run playground-website:dev',
		url: 'http://127.0.0.1:5400/website-server/',
		reuseExistingServer: !process.env.CI,
	},
};

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig(playwrightConfig);
