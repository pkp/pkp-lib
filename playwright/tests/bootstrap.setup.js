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
 *   2. seed     — POST baseline spec to /api/v1/_test/scenarios/journal,
 *                 skipped if the publicknowledge journal already resolves.
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

	// Hard-stop if OJS_DB_HOST isn't local. The bootstrap seeds a known
	// `admin` / `admin` login on the database to match Cypress's
	// baseline; pointing this harness at a remote / shared / public DB
	// would mint that login on something exposed. Local-only is a
	// non-negotiable invariant.
	const dbHost = (process.env.OJS_DB_HOST || '').trim();
	const localHosts = new Set(['localhost', '127.0.0.1', '::1', '0.0.0.0']);
	if (!localHosts.has(dbHost)) {
		throw new Error(
			`bootstrap refuses to install: OJS_DB_HOST=${JSON.stringify(dbHost)} ` +
				'is not a local hostname. The Playwright bootstrap seeds an ' +
				'admin/admin login that must never reach a non-local DB. Set ' +
				'OJS_DB_HOST to localhost / 127.0.0.1 / ::1.',
		);
	}

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
