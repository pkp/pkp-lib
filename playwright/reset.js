// @ts-check
/**
 * @file lib/pkp/playwright/reset.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Force a cold bootstrap: drop and reinstall the test database, then throw away
 * every cached storage state.
 *
 * The two halves belong together. Cached sessions point at rows in the database
 * that just stopped existing, and a storage state that authenticates against a
 * DIFFERENT installation is the nastiest kind of stale: the liveness probe
 * catches it, but only after a confusing run. Wiping `.auth/` here makes that
 * impossible instead of merely recoverable.
 *
 * Run from an app repository root:
 *
 *     node lib/pkp/playwright/reset.js
 */

const fs = require('fs');
const path = require('path');
const {execSync} = require('child_process');
const {loadEnvFile} = require('./config-factory.js');

const appRoot = process.cwd();

loadEnvFile(path.join(appRoot, '.env.playwright'));

const configFile =
	process.env.PKP_CONFIG_FILE || path.join(appRoot, 'config.test.inc.php');

if (!fs.existsSync(configFile)) {
	console.error(
		`No test configuration at ${configFile}.\n` +
			'Set PKP_CONFIG_FILE in .env.playwright (copy .env.playwright.example).',
	);
	process.exit(1);
}

console.log(`Recreating the test database named in ${configFile}`);

execSync('php tools/installTest.php --recreate-db', {
	cwd: appRoot,
	env: {...process.env, PKP_CONFIG_FILE: configFile},
	stdio: 'inherit',
});

const authDir = path.join(appRoot, 'playwright', '.auth');

if (fs.existsSync(authDir)) {
	fs.rmSync(authDir, {recursive: true, force: true});
	console.log(`Cleared cached storage states in ${authDir}`);
}

console.log('Done. The next `npm run test:e2e:setup` will seed from cold.');
