// @ts-check
const {BasePage} = require('./BasePage.js');

/**
 * POM for the post-login landing page. Shared across apps — the
 * dashboard layout is driven by pkp-lib.
 */
exports.DashboardPage = class DashboardPage extends BasePage {
	constructor(page) {
		super(page);
		this.heading = page.getByRole('heading', {name: 'Dashboard'});
	}

	async goto() {
		await this.page.goto('/dashboard');
	}
};
