// @ts-check

const {execFile} = require('child_process');
const {promisify} = require('util');
const fs = require('fs');
const path = require('path');
const {test} = require('../support/base-test.js');
const {baselineUsers} = require('../data/users.js');
const {saveAuthStates} = require('../support/auth.js');

const execFileAsync = promisify(execFile);

/**
 * Serial bootstrap — gates every feature test.
 *
 * Three idempotent stages:
 *   1. install  — CLI spawn of tools/installTest.php, skipped if OJS
 *                 already responds outside of the install redirect.
 *   2. seed     — POST baseline spec to /api/v1/_test/bootstrap, skipped
 *                 if the publicknowledge journal already resolves.
 *   3. auth     — log each baseline user in and persist storage state,
 *                 skipped if every .auth/<user>.json file already exists.
 *
 * Warm-path runs (everything already set up) complete in under a second
 * total, so repeated `npx playwright test tests/foo.spec.js` feels
 * instant without requiring manual project filtering.
 *
 * Fresh state is a manual operation: drop the database + clear files dir
 * + restore config.inc.php from the template, then run again.
 */
test.describe.configure({mode: 'serial'});

const APP_ROOT = process.cwd();
const BOOTSTRAP_SPEC = path.join(APP_ROOT, 'playwright/fixtures/bootstrap.js');
const AUTH_DIR = path.join(APP_ROOT, 'playwright/.auth');

test('installs OJS', async ({pkpApi}) => {
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

	// The fixture assembles journal metadata + imports baselineUsers
	// from lib/pkp/playwright/data/users.js.
	const spec = require(BOOTSTRAP_SPEC);
	const result = await pkpApi.bootstrap(spec);
	test.info().attach('bootstrap-result', {
		body: JSON.stringify(result, null, 2),
		contentType: 'application/json',
	});
});

test('saves auth storage states', async ({browser}) => {
	const allPresent = baselineUsers.every((user) =>
		fs.existsSync(path.join(AUTH_DIR, `${user.username}.json`)),
	);
	if (allPresent) {
		test.info().annotations.push({
			type: 'skip-reason',
			description: 'All .auth/*.json files already present',
		});
		return;
	}

	await saveAuthStates(browser, baselineUsers, AUTH_DIR);
});
