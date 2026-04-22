// @ts-check
const {test} = require('../support/base-test.js');
const {users} = require('../data/users.js');
const {saveAuthStates} = require('../support/auth.js');

/**
 * Serial bootstrap — gates every feature test.
 *
 * Single-file, `serial` describe mode: Playwright runs these steps in
 * declaration order within one worker, giving the later "skip if already
 * installed" optimisation a single place to live. The whole file is
 * matched by the `setup` project in the shared config, and every feature
 * project has `dependencies: ['setup']` so this always runs first.
 *
 * Test bodies are empty declaration-level `test.fixme` placeholders. Real
 * implementations land in follow-up PRs, likely alongside the new OJS
 * test-scenario APIs. Convert `test.fixme(...)` to `test(...)` when the
 * step is ready.
 */
test.describe.configure({mode: 'serial'});

test.fixme('installs OJS', async ({page}) => {
	// TODO: port cy.install() — drop DB, run installer form
});

test.fixme('creates journal (context)', async ({page, pkpApi}) => {
	// TODO: admin login, create JPK context via setup wizard
});

test.fixme('creates users', async ({pkpApi}) => {
	// TODO: API-based factory for all roles in data/users.js
});

test.fixme('creates categories', async ({page}) => {
	// TODO: Applied Science + subcategories
});

test.fixme('creates sections', async ({page}) => {
	// TODO: Articles, Reviews (OJS-specific)
});

test.fixme('saves auth storage states', async ({browser}) => {
	// TODO: saveAuthStates(browser, users, 'playwright/.auth')
});
