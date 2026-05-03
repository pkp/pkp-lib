// @ts-check
const base = require('@playwright/test');
const {createApiClient} = require('./api.js');
const {createMailClient} = require('./mail.js');
const {ensureAuthStateFor} = require('./auth.js');

/**
 * Shared extended `test` — every spec (shared or app-specific) ultimately
 * derives from here. OJS's playwright/support/fixtures.js layers OJS-only
 * fixtures on top; OMP/OPS do the same in their own repos.
 *
 * Fixtures provided:
 *   baseURL      — overrides Playwright's built-in fixture. Each parallel
 *                  worker gets its own dedicated PHP dev server on port
 *                  8000 + parallelIndex (see config-factory.js webServer
 *                  array). The default `use.baseURL` in the config still
 *                  points at port 8000 for the setup project + as a
 *                  fallback. PLAYWRIGHT_BASE_URL env var, if set, takes
 *                  priority — useful when targeting an external server
 *                  (e.g. for debugging against a manually-started PHP).
 *   pkpApi       — cross-app HTTP client (login, CSRF, context endpoints)
 *   pkpMail      — Mailpit HTTP API wrapper. Tests that assert on mail
 *                  sent during normal app requests destructure this
 *                  fixture and call `clearAll()` / `inboxFor(email)` /
 *                  `fullMessage(id)`. Scenario-seeding mail does NOT
 *                  reach Mailpit — Mail::fake() in the scenario
 *                  controllers discards it.
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
	baseURL: async ({}, use, testInfo) => {
		// Explicit env var overrides everything (debug / external server).
		if (process.env.PLAYWRIGHT_BASE_URL) {
			await use(process.env.PLAYWRIGHT_BASE_URL);
			return;
		}
		// Otherwise, route this worker to its dedicated PHP server.
		// parallelIndex is 0 for the setup project (single worker) and
		// 0..N-1 for the main project's parallel workers; the
		// webServer array spawns matching ports.
		const port = 8000 + testInfo.parallelIndex;
		await use(`http://127.0.0.1:${port}`);
	},

	pkpApi: async ({request, baseURL}, use) => {
		await use(createApiClient({request, baseURL}));
	},
	pkpMail: async ({request}, use) => {
		await use(createMailClient({request}));
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
