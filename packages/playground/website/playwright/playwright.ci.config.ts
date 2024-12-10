import { defineConfig } from '@playwright/test';
import playwrightConfig from './playwright.config';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
	...playwrightConfig,
	use: {
		...playwrightConfig.use,
		baseURL: 'http://127.0.0.1/',
	},
	webServer: {
		command:
			'nx run playground-php-cors-proxy:start& npx nx run playground-website:preview:ci',
		url: 'http://127.0.0.1/',
		reuseExistingServer: false,
	},
});
