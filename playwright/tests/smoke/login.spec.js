// @ts-check
const {test, expect} = require('../../support/base-test.js');
const {DashboardPage} = require('../../pages/DashboardPage.js');

/**
 * Shared smoke test. Proves the runner picks up specs from
 * lib/pkp/playwright/tests/** and that the admin storage-state produced
 * by bootstrap lets us skip login.
 */
test.use({storageState: 'playwright/.auth/admin.json'});

// Declaration-level test.fixme — the body never runs and storageState is not
// loaded. Convert to a regular `test(...)` once bootstrap seeds the .auth
// files and the real assertion is ready.
test.fixme('admin lands on dashboard', async ({page}) => {
	// TODO: new DashboardPage(page).goto(); assert heading visible
});
