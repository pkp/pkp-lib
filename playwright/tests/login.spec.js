// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Shared login smoke. Proves three things end-to-end:
 *   - bootstrap seeded playwright/.auth/admin.json with a valid session
 *   - the running PHP server accepts that session cookie
 *   - our storageState plumbing skips the /login round-trip
 *
 * Tag convention (filter on the CLI with `--grep @smoke`):
 *   @smoke      — minimal coverage, must-pass on every PR
 *   @regression — broader coverage, typically scheduled / nightly
 *   @slow       — opt-out for fast local runs
 *   @flaky      — quarantined; excluded from default runs
 * Tests can carry multiple tags: {tag: ['@smoke', '@critical']}.
 */
test.use({user: 'admin'});

test(
	'admin visits site root without being redirected to /login',
	{tag: '@smoke'},
	async ({page}) => {
		await page.goto('/');
		// If the session cookie is valid, OJS serves an authenticated page.
		// If it's not, OJS redirects to the login form — the cheapest and
		// most robust failure signal for "our auth pipeline is broken".
		await expect(page).not.toHaveURL(/\/login/);
	},
);
