// @ts-check
const {test, expect} = require('../support/base-test.js');
const {DashboardPage} = require('../pages/DashboardPage.js');

/**
 * Shared login smoke. Proves the runner picks up specs from
 * lib/pkp/playwright/tests/** and that the admin storage-state produced
 * by bootstrap lets us skip login.
 *
 * Tag convention (filter on the CLI with `--grep @smoke`):
 *   @smoke      — minimal coverage, must-pass on every PR
 *   @regression — broader coverage, typically scheduled / nightly
 *   @slow       — opt-out for fast local runs
 *   @flaky      — quarantined; excluded from default runs
 * Tests can carry multiple tags: {tag: ['@smoke', '@critical']}.
 */
test.use({storageState: 'playwright/.auth/admin.json'});

// Declaration-level test.fixme — body never runs and storageState is not
// loaded. Convert to a regular `test(...)` once bootstrap seeds .auth
// files and the real assertion is ready.
test.fixme(
	'admin lands on dashboard',
	{tag: '@smoke'},
	async ({page}) => {
		// TODO: new DashboardPage(page).goto(); assert heading visible
	},
);
