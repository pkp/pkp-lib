// @ts-check
/**
 * @file lib/pkp/playwright/pages/DashboardPage.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The post-login landing: the submissions dashboard. Three views, gated by role
 * — editorial (manager/editor/assistant), review assignments (reviewer) and my
 * submissions (author) — all under the same context-scoped `dashboard` page.
 */

const {BasePage} = require('./BasePage.js');

class DashboardPage extends BasePage {
	/**
	 * @param {import('@playwright/test').Page} page
	 * @param {string} contextPath url path of the context whose dashboard this is
	 */
	constructor(page, contextPath) {
		super(page);
		this.contextPath = contextPath;
		this.main = page.getByRole('main');

		// The dashboard's own H1 names the CURRENT VIEW, not the page ("Assigned
		// to me (0)"), and the view depends on the role and the app. So the
		// landmark is "the main region has its heading", which is true on every
		// view in every app and false on the login page — which is exactly what a
		// smoke needs to distinguish.
		this.heading = this.main.getByRole('heading', {level: 1});
		this.searchBox = this.main.getByRole('searchbox');
	}

	/** @param {'editorial'|'reviewAssignments'|'mySubmissions'} view */
	url(view) {
		return BasePage.contextUrl(this.contextPath, `/dashboard/${view}`);
	}

	/** @param {'editorial'|'reviewAssignments'|'mySubmissions'} view */
	async goto(view) {
		return this.page.goto(this.url(view));
	}
}

module.exports = {DashboardPage};
