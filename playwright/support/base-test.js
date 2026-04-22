// @ts-check
const base = require('@playwright/test');
const {createApiClient} = require('./api.js');
const {createScenarioClient} = require('./scenarios.js');

/**
 * Shared extended `test` — every spec (shared or app-specific) ultimately
 * derives from here. OJS's playwright/support/fixtures.js layers OJS-only
 * fixtures on top; OMP/OPS do the same in their own repos.
 *
 * Fixtures provided:
 *   pkpApi     — cross-app HTTP client (login, CSRF, context endpoints)
 *   scenarios  — SEAM for the forthcoming OJS test-scenario API; stub
 *                today, one-file wire-up when the backend endpoints land.
 */
exports.test = base.test.extend({
	pkpApi: async ({request, baseURL}, use) => {
		await use(createApiClient({request, baseURL}));
	},
	scenarios: async ({request, baseURL}, use) => {
		await use(createScenarioClient({request, baseURL}));
	},
});

exports.expect = base.expect;
