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
 *
 * This spec is shared, so it names no usernames: the actors come from
 * `appContext.seed.actors`, and the single-actor `user` option takes the
 * DEFAULT_EDITORIAL_USER sentinel rather than a literal. OPS is what makes that
 * mandatory — it has no editor group and no reviewer group, so `actors.editor`
 * and `actors.reviewer` are null there.
 */

const {test, expect, DEFAULT_EDITORIAL_USER} = require('../support/base-test.js');
const {DashboardPage} = require('../pages/DashboardPage.js');

test.describe('login smoke', () => {
	// Consumption path 1: the `user` option, resolved by the `storageState`
	// fixture, so `page` simply arrives logged in. Runs in every app.
	test.use({user: DEFAULT_EDITORIAL_USER});

	test('a seeded editorial user lands on the editorial dashboard', {tag: '@smoke'}, async ({
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
		const {contextPath, actors} = appContext.seed;
		const dashboard = new DashboardPage(page, contextPath);

		await dashboard.goto('editorial');
		await expect(dashboard.heading).toBeVisible();

		// Consumption path 2: a second, independently authenticated browser
		// context — the shape every multi-actor test uses. It closes itself at
		// teardown. The author archetype is seeded in all three apps, so this
		// path is covered everywhere, including where the reviewer test below
		// skips.
		const authorContext = await asUser(actors.author);
		const authorPage = await authorContext.newPage();
		const authorDashboard = new DashboardPage(authorPage, contextPath);

		await authorDashboard.goto('mySubmissions');

		await expect(authorDashboard.heading).toBeVisible();
		await expect(authorPage).not.toHaveURL(/\/login/);
	});

	test('a reviewer reaches their own review queue', {tag: '@smoke'}, async ({
		asUser,
		appContext,
	}) => {
		test.skip(
			!appContext.capabilities.hasReviewerRoles,
			'This app seeds no reviewer group.',
		);

		const {contextPath, actors} = appContext.seed;
		const reviewerContext = await asUser(actors.reviewer);
		const reviewerPage = await reviewerContext.newPage();
		const reviewerDashboard = new DashboardPage(reviewerPage, contextPath);

		await reviewerDashboard.goto('reviewAssignments');

		await expect(reviewerDashboard.heading).toBeVisible();
		await expect(reviewerPage).not.toHaveURL(/\/login/);
	});
});
