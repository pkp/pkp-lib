// @ts-check
/**
 * @file lib/pkp/playwright/config-factory.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The Playwright configuration shared by OJS, OMP and OPS.
 *
 * Each app's playwright/playwright.config.js calls definePkpConfig() with its
 * own root, name and base port; everything else — projects, per-worker servers,
 * env loading — is identical across the three fleets so that a change here
 * reaches all of them.
 *
 * ## Per-worker servers
 *
 * `php -S` is single threaded: one request at a time, per process. A single
 * server shared by N Playwright workers therefore serialises the whole suite and
 * (worse) lets one worker's slow request stall another worker's page load into a
 * timeout. So each parallel worker gets its OWN server process on its own port:
 *
 *     port = basePort + testInfo.parallelIndex        (8000, 8001, 8002, ...)
 *
 * `parallelIndex` — not `workerIndex` — is the right key: it is guaranteed to
 * stay inside [0, workers), while workerIndex keeps growing when a worker is
 * replaced after a crash. The factory starts exactly `workers` servers through
 * Playwright's `webServer` array and `support/base-test.js` resolves the port
 * from the parallel index, so the two always agree.
 *
 * All those servers talk to the SAME database and files directory — they are
 * one installation served by several processes, not several installations. That
 * is safe because the app derives its base URL from the request host (config
 * `base_url` is only consulted for command-line calls) and `allowed_hosts` is
 * matched with the port stripped, so `127.0.0.1` covers every worker port.
 * Session cookies are host-scoped, not port-scoped, which is also why one cached
 * storage state authenticates a user on every worker's port.
 */

const fs = require('fs');
const path = require('path');
const {defineConfig, devices} = require('@playwright/test');

/**
 * Minimal .env reader.
 *
 * The harness needs a handful of variables to reach PHP's process environment
 * (PKP_CONFIG_FILE, TEST_API_KEY); a dependency for that would be a dependency
 * the three apps all have to carry. Existing environment variables always win,
 * so an inline `TEST_API_KEY=… npx playwright test` overrides the file.
 */
function loadEnvFile(file) {
	if (!fs.existsSync(file)) {
		return;
	}

	for (const rawLine of fs.readFileSync(file, 'utf8').split('\n')) {
		const line = rawLine.trim();

		if (!line || line.startsWith('#')) {
			continue;
		}

		const separator = line.indexOf('=');

		if (separator === -1) {
			continue;
		}

		const name = line.slice(0, separator).trim();
		const value = line
			.slice(separator + 1)
			.trim()
			.replace(/^(['"])(.*)\1$/, '$2');

		if (!(name in process.env)) {
			process.env[name] = value;
		}
	}
}

/**
 * @param {object} options
 * @param {string} options.appName        Project name and the app's own suite ('ojs', 'omp', 'ops')
 * @param {string} options.appRoot        Absolute path to the app repository root
 * @param {number} options.basePort       Port of worker 0 (OJS 8000 / OMP 8100 / OPS 8200)
 * @param {string} [options.host]         Interface the servers bind to
 * @param {string} [options.readinessPath] Path the webServer readiness probe hits
 */
function definePkpConfig({
	appName,
	appRoot,
	basePort,
	host = '127.0.0.1',
	readinessPath = '/favicon.ico',
}) {
	loadEnvFile(path.join(appRoot, '.env.playwright'));

	// PLAYWRIGHT_BASE_URL names worker 0's server; the other workers still sit at
	// the ports above it. Set it to point a run at a server you started yourself.
	if (process.env.PLAYWRIGHT_BASE_URL) {
		const url = new URL(process.env.PLAYWRIGHT_BASE_URL);
		host = url.hostname;
		basePort = Number(url.port || 80);
	}

	const sharedRoot = path.join(appRoot, 'lib', 'pkp', 'playwright');
	const appPlaywrightRoot = path.join(appRoot, 'playwright');
	const workers = Number(process.env.PLAYWRIGHT_WORKERS || 2);
	const configFile =
		process.env.PKP_CONFIG_FILE || path.join(appRoot, 'config.test.inc.php');

	// One single-threaded PHP server per parallel worker. Readiness is probed on
	// a static file so that "the server is up" never depends on the database
	// being installed — the setup project is what installs it.
	const webServer = Array.from({length: workers}, (unused, index) => {
		const port = basePort + index;

		return {
			command: `php -S ${host}:${port} -t ${appRoot}`,
			url: `http://${host}:${port}${readinessPath}`,
			cwd: appRoot,
			env: {
				PKP_CONFIG_FILE: configFile,
				TEST_API_KEY: process.env.TEST_API_KEY || '',
			},
			reuseExistingServer: !process.env.CI,
			stdout: 'ignore',
			stderr: 'pipe',
			timeout: 60_000,
		};
	});

	return defineConfig({
		// Never scanned: every project below declares its own testDir.
		testDir: appPlaywrightRoot,
		fullyParallel: true,
		forbidOnly: !!process.env.CI,
		retries: process.env.CI ? 1 : 0,
		workers,
		reporter: process.env.CI ? 'html' : 'list',
		outputDir: path.join(appPlaywrightRoot, '.results'),
		timeout: 60_000,
		expect: {timeout: 10_000},

		use: {
			...devices['Desktop Chrome'],
			trace: 'retain-on-failure',
			screenshot: 'only-on-failure',
			video: 'off',
			actionTimeout: 15_000,

			// Options declared by support/base-test.js. baseURL itself is derived
			// per worker from pkpServer; nothing sets it directly.
			pkpServer: {host, basePort},
			authDir: path.join(appPlaywrightRoot, '.auth'),
			appContextModule: path.join(
				appPlaywrightRoot,
				'support',
				'app.context.js',
			),
			testApiKey: process.env.TEST_API_KEY || '',
			mailpitUrl: process.env.MAILPIT_URL || 'http://127.0.0.1:8025',
			installCommand:
				process.env.PKP_INSTALL_COMMAND || 'php tools/installTest.php',
			appRoot,
			configFile,
		},

		projects: [
			{
				// Cold: install the schema if needed, then POST the base seed.
				// Warm: a single GET probe and out.
				name: 'setup',
				testDir: path.join(sharedRoot, 'tests'),
				testMatch: /.*\.setup\.js/,
			},
			{
				// The app-agnostic infrastructure specs (login smoke).
				name: 'shared',
				testDir: path.join(sharedRoot, 'tests'),
				testMatch: /.*\.spec\.js/,
				dependencies: ['setup'],
			},
			{
				// This app's feature suites — flat, no subfolder taxonomy.
				name: appName,
				testDir: path.join(appPlaywrightRoot, 'tests'),
				testIgnore: /serial\/.*/,
				dependencies: ['setup'],
			},
			{
				// Globally-scanning work (scheduled tasks, site settings, plugin
				// toggles, cache clears) runs alone at the end. It may be empty;
				// the project exists so a spec that needs it has a home.
				name: `${appName}-serial`,
				testDir: path.join(appPlaywrightRoot, 'tests', 'serial'),
				fullyParallel: false,
				workers: 1,
				dependencies: [appName, 'shared'],
			},
		],

		webServer,
	});
}

module.exports = {definePkpConfig, loadEnvFile};
