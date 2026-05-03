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
		// Per-worker routing: each parallel worker gets its own PHP server
		// on port 8000 + parallelIndex. The webServer array in
		// config-factory.js spawns matching ports.
		//
		// Override: PLAYWRIGHT_BASE_URL still works for "debug against an
		// external server" use cases (e.g. point all workers at a manually-
		// started PHP, or a remote staging URL). But — and this is
		// important — we *ignore* PLAYWRIGHT_BASE_URL when it points at
		// any 127.0.0.1/localhost port. That defends against a stale
		// .env.playwright (the example used to set
		// PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000 by default; copies
		// of that file linger after upgrades and would silently route
		// every worker to the same port, defeating multi-server
		// distribution). Only a clearly-external URL counts as an
		// intentional override.
		const envOverride = process.env.PLAYWRIGHT_BASE_URL;
		if (envOverride && !/^https?:\/\/(127\.0\.0\.1|localhost)(:|\/|$)/i.test(envOverride)) {
			await use(envOverride);
			return;
		}
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
