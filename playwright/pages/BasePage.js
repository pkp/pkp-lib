// @ts-check

/**
 * Base class for every Page Object Model (POM) in the Playwright suite.
 *
 * Holds the Playwright Page instance and hosts helpers shared by all page
 * objects across OJS / OMP / OPS (notifications, modals, global nav).
 * App-specific page objects extend this class either directly or via
 * another shared POM (LoginPage, DashboardPage, etc.).
 */
exports.BasePage = class BasePage {
	/** @param {import('@playwright/test').Page} page */
	constructor(page) {
		this.page = page;
	}

	async dismissAllNotifications() {
		// TODO: port cy notification helpers
	}
};
