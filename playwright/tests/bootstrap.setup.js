// @ts-check

const {execFile} = require('child_process');
const {promisify} = require('util');
const path = require('path');
const {test} = require('../support/base-test.js');

const execFileAsync = promisify(execFile);

/**
 * Serial bootstrap — gates every feature test.
 *
 * Two idempotent stages:
 *   1. install  — CLI spawn of tools/installTest.php, skipped if OJS
 *                 already responds outside of the install redirect.
 *   2. seed     — POST baseline spec to /api/v1/_test/bootstrap, skipped
 *                 if the publicknowledge journal already resolves.
 *
 * Auth is handled on-demand by the `storageState` fixture in
 * support/base-test.js (see support/auth.js::ensureAuthStateFor): each
 * spec that declares `test.use({user: 'dbarnes'})` triggers a login for
 * that user on first use and caches the result in playwright/.auth/.
 * That avoids the ~16s upfront cost of logging in every baseline user
 * regardless of whether any spec actually needs them.
 *
 * Warm-path runs (everything already set up) complete in under a second
 * total, so repeated `npx playwright test tests/foo.spec.js` feels
 * instant without requiring manual project filtering.
 *
 * Fresh state: `npm run test:e2e:reset`.
 */
test.describe.configure({mode: 'serial'});

const APP_ROOT = process.cwd();
const BOOTSTRAP_SPEC = path.join(APP_ROOT, 'playwright/fixtures/bootstrap.js');

test('installs OJS', async ({pkpApi}) => {
	// Cold install migrates the full schema, which blows past the 60s
	// default on a fresh DB.
	test.setTimeout(300_000);

	if (await pkpApi.isInstalled()) {
		test.info().annotations.push({
			type: 'skip-reason',
			description: 'OJS already installed',
		});
		return;
	}

	const {stdout, stderr} = await execFileAsync(
		'php',
		['tools/installTest.php'],
		{
			cwd: APP_ROOT,
			maxBuffer: 10 * 1024 * 1024,
			env: {...process.env, APPLICATION_ENV: 'test'},
		},
	);
	test.info().attach('install-stdout', {body: stdout, contentType: 'text/plain'});
	if (stderr) {
		test.info().attach('install-stderr', {body: stderr, contentType: 'text/plain'});
	}
	if (!/Successfully installed/.test(stdout)) {
		throw new Error(
			'Install completed without the expected "Successfully installed" line. See attached stdout.',
		);
	}
});

test('seeds baseline (journal, users, sections, categories, issues)', async ({pkpApi}) => {
	if (await pkpApi.isBootstrapped()) {
		test.info().annotations.push({
			type: 'skip-reason',
			description: 'Baseline already seeded (publicknowledge journal exists)',
		});
		return;
	}

	const spec = require(BOOTSTRAP_SPEC);
	const result = await pkpApi.bootstrap(spec);
	test.info().attach('bootstrap-result', {
		body: JSON.stringify(result, null, 2),
		contentType: 'application/json',
	});
});
