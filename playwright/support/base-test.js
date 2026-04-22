// @ts-check
const base = require('@playwright/test');
const {createApiClient} = require('./api.js');
const {createScenarioClient} = require('./scenarios.js');
const {ensureAuthStateFor} = require('./auth.js');

/**
 * Shared extended `test` — every spec (shared or app-specific) ultimately
 * derives from here. OJS's playwright/support/fixtures.js layers OJS-only
 * fixtures on top; OMP/OPS do the same in their own repos.
 *
 * Fixtures provided:
 *   pkpApi       — cross-app HTTP client (login, CSRF, context endpoints)
 *   scenarios    — SEAM for the forthcoming OJS test-scenario API; stub
 *                  today, one-file wire-up when the backend endpoints land.
 *   user         — option fixture; specs declare `test.use({user: 'dbarnes'})`.
 *                  Omit or set to undefined for an anonymous context.
 *   storageState — overrides Playwright's built-in fixture. Looks up the
 *                  current `user`, lazily logs them in via auth.js on
 *                  first use, and returns the cached storage-state path.
 *                  Login happens once per user per DB lifetime — the file
 *                  on disk is the cache.
 *   asUser       — async (username) => BrowserContext. For multi-actor
 *                  tests that need more than the default-user context.
 *                  Shares auth.js's cache and auto-closes contexts at
 *                  test teardown.
 */
exports.test = base.test.extend({
	pkpApi: async ({request, baseURL}, use) => {
		await use(createApiClient({request, baseURL}));
	},
	scenarios: async ({request, baseURL}, use) => {
		await use(createScenarioClient({request, baseURL}));
	},

	user: [undefined, {option: true}],

	storageState: async ({user, browser, baseURL}, use) => {
		if (!user) {
			await use(undefined);
			return;
		}
		await use(await ensureAuthStateFor(browser, user, {baseURL}));
	},

	asUser: async ({browser, baseURL}, use) => {
		/** @type {import('@playwright/test').BrowserContext[]} */
		const opened = [];
		await use(async (username) => {
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, username, {baseURL}),
				baseURL,
			});
			opened.push(ctx);
			return ctx;
		});
		// Teardown: close every context the test opened. Swallow errors —
		// a test may have closed one explicitly, which is fine.
		for (const ctx of opened) {
			await ctx.close().catch(() => {});
		}
	},
});

exports.expect = base.expect;
