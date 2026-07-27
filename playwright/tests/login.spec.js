// @ts-check
/**
 * @file lib/pkp/playwright/tests/login.spec.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The suite's smoke test: the harness can put a seeded user in front of the
 * dashboard, in one context and in two.
 *
 * Everything else in the suite assumes this works, which is why it is the one
 * spec worth running on its own when something looks broken: a red login smoke
 * says the seed, the auth cache or the servers are wrong, not the feature under
 * test.
 *
 * NOT covered here: the login FORM's own behaviour (bad credentials, lost
 * password, remember me, disabled accounts). That is a feature of the app and
 * belongs to a per-app suite, not to the infrastructure layer.
 */

const {test, expect} = require('../support/base-test.js');
const {DashboardPage} = require('../pages/DashboardPage.js');

test.describe('login smoke', () => {
	test.use({user: 'editor.diana'});

	test('a seeded editor lands on the editorial dashboard', {tag: '@smoke'}, async ({
		page,
		appContext,
	}) => {
		const dashboard = new DashboardPage(page, appContext.seed.contextPath);

		await dashboard.goto('editorial');

		await expect(dashboard.heading).toBeVisible();
		await expect(page).not.toHaveURL(/\/login/);
	});

	test('a second actor is authenticated alongside the default one', {tag: '@smoke'}, async ({
		page,
		asUser,
		appContext,
	}) => {
		test.skip(
			!appContext.capabilities.hasReviewerRoles,
			'This app seeds no reviewer group.',
		);

		const {contextPath, actors} = appContext.seed;
		const dashboard = new DashboardPage(page, contextPath);

		await dashboard.goto('editorial');
		await expect(dashboard.heading).toBeVisible();

		// A second, independently authenticated browser context — the shape every
		// multi-actor test uses. It closes itself at teardown.
		const reviewerContext = await asUser(actors.reviewer);
		const reviewerPage = await reviewerContext.newPage();
		const reviewerDashboard = new DashboardPage(reviewerPage, contextPath);

		await reviewerDashboard.goto('reviewAssignments');

		await expect(reviewerDashboard.heading).toBeVisible();
		await expect(reviewerPage).not.toHaveURL(/\/login/);
	});
});
