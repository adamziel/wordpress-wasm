import { Page, expect } from '@playwright/test';

export class WebsitePage {
	constructor(public readonly page: Page) {}

	// Wait for WordPress to load
	async waitForNestedIframes(page = this.page) {
		await expect(
			page
				/* There are multiple viewports possible, so we need to select
				   the one that is visible. */
				.frameLocator(
					'#playground-viewport:visible,.playground-viewport:visible'
				)
				.frameLocator('#wp')
				.locator('body')
		).not.toBeEmpty();
	}

	wordpress(page = this.page) {
		return (
			page
				/* There are multiple viewports possible, so we need to select
			   the one that is visible. */
				.frameLocator(
					'#playground-viewport:visible,.playground-viewport:visible'
				)
				.frameLocator('#wp')
		);
	}

	async goto(url: string, options?: any) {
		const originalGoto = this.page.goto.bind(this.page);
		const response = await originalGoto(url, options);
		await this.waitForNestedIframes();
		return response;
	}

	async ensureSiteManagerIsOpen() {
		const siteManagerHeading = this.page.locator('.main-sidebar');
		if (await siteManagerHeading.isHidden({ timeout: 5000 })) {
			await this.page.getByLabel('Open Site Manager').click();
		}
		await expect(siteManagerHeading).toBeVisible();
	}

	async ensureSiteManagerIsClosed() {
		const openSiteButton = this.page.locator('div[title="Open site"]');
		if (await openSiteButton.isVisible({ timeout: 5000 })) {
			await openSiteButton.click();
		}
		const siteManagerHeading = this.page.locator('.main-sidebar');
		await expect(siteManagerHeading).not.toBeVisible();
	}

	async getSiteTitle(): Promise<string> {
		return await this.page
			.locator('h1[class*="_site-info-header-details-name"]')
			.innerText();
	}
}
