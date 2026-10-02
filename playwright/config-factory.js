// @ts-check
const os = require('os');
const path = require('path');
const {defineConfig, devices} = require('@playwright/test');

/**
 * Shared Playwright config factory for OJS / OMP / OPS.
 *
 * Each app's root playwright.config.js is a 3-line stub that calls this
 * factory with its own name ('ojs' / 'omp' / 'ops'). All real config
 * logic lives here so the three apps never drift.
 *
 * Parameters:
 *   app — short app name; becomes the Playwright project name used on
 *         the CLI (e.g. `playwright test --project=ojs`).
 */
module.exports = function createPlaywrightConfig({app}) {
	const appRoot = process.cwd();
	require('dotenv').config({path: path.join(appRoot, '.env.playwright')});
	const isCI = !!process.env.CI;

	// Worker count resolution (in priority order):
	//   1. `--workers=N` CLI flag — Playwright's canonical knob, parsed
	//      out of process.argv so the spawn count below stays in sync
	//      (Playwright applies --workers AFTER this config evaluates,
	//      otherwise we'd just read it from the resolved config).
	//   2. PLAYWRIGHT_WORKERS env var — convenient for npm scripts /
	//      .env files where threading a CLI flag is awkward.
	//   3. CI: 3 (matches the historical default).
	//   4. Local: ceil(cpus / 2) — same heuristic Playwright's own
	//      default uses, with a floor of 1.
	//
	// The chosen count drives both the Playwright `workers` setting AND
	// the number of `php -S` instances spawned by `webServer` below.
	// Each Playwright worker is paired 1:1 with a dedicated PHP server
	// on a unique port (8000, 8001, 8002, …). This avoids the
	// PHP_CLI_SERVER_WORKERS env var which is Unix-only — Windows PHP
	// ignores it, and the resulting single-process dev server deadlocks
	// on same-origin sub-requests (page loads fetching their own /api).
	const argvWorkers = (() => {
		const argv = process.argv;
		for (let i = 0; i < argv.length; i++) {
			if (argv[i] === '--workers' && argv[i + 1]) {
				const n = parseInt(argv[i + 1], 10);
				if (Number.isFinite(n) && n > 0) return n;
			}
			const m = argv[i].match(/^--workers=(\d+)$/);
			if (m) return parseInt(m[1], 10);
		}
		return null;
	})();
	const playwrightWorkers = (() => {
		if (argvWorkers) return argvWorkers;
		const override = parseInt(process.env.PLAYWRIGHT_WORKERS ?? '', 10);
		if (Number.isFinite(override) && override > 0) {
			return override;
		}
		if (isCI) return 3;
		return Math.max(1, Math.ceil(os.cpus().length / 2));
	})();
	const phpPorts = Array.from(
		{length: playwrightWorkers},
		(_, i) => 8000 + i,
	);

	return defineConfig({
		testDir: appRoot,
		testMatch: [
			'playwright/tests/**/*.spec.js',
			'playwright/tests/**/*.setup.js',
			'lib/pkp/playwright/tests/**/*.spec.js',
			'lib/pkp/playwright/tests/**/*.setup.js',
		],
		fullyParallel: true,
		forbidOnly: isCI,
		retries: isCI ? 1 : 0,
		workers: playwrightWorkers,
		reporter: isCI
			? [['github'], ['html', {open: 'never'}]]
			: [['list'], ['html', {open: 'never'}]],
		outputDir: path.join(appRoot, 'test-results'),
		// Test-level timeout: hard cap on a single test's total runtime.
		// Catches genuine hangs (infinite loops, dead waits). Stays at
		// 60s — that's plenty for any single test's full UI flow.
		timeout: 60_000,
		// Expect / action timeouts absorb single-request latency tails.
		// With one PHP process per worker (no PHP_CLI_SERVER_WORKERS on
		// Windows), browser-driven parallel sub-requests serialize on
		// the dev server. Empirically ~2% of HTTP connections see a
		// 5-16s wait under heavy load (mostly dashboard mount API
		// calls + the static assets queued behind them). Bumping
		// per-action waits to 20s absorbs that tail; the test-level
		// 60s timeout still catches genuine hangs.
		expect: {timeout: 20_000},
		use: {
			// Default points at the first PHP server (port 8000) — used
			// by the setup project (which runs single-worker on
			// parallelIndex=0) and as a fallback. The main project
			// overrides this per-worker via the baseURL fixture in
			// lib/pkp/playwright/support/base-test.js so each parallel
			// worker hits its own dedicated PHP server.
			baseURL: `http://127.0.0.1:${phpPorts[0]}`,
			actionTimeout: 20_000,
			navigationTimeout: 45_000,
			trace: 'retain-on-failure',
			video: isCI ? 'retain-on-failure' : 'off',
			screenshot: 'only-on-failure',
			// Force `prefers-reduced-motion: reduce` on every browser
			// context — both the default one Playwright creates for the
			// `page` fixture AND any manually-created context via
			// `browser.newContext(...)`. The lib/ui-library modal/dialog
			// styles collocate `@media (prefers-reduced-motion: reduce)`
			// blocks that nullify slide/fade animations, saving
			// ~300–450 ms per side-modal open or close (and removing
			// parallel-load flake from animation timing). Setting this
			// under `contextOptions` (rather than top-level
			// `use.reducedMotion`) is the propagating form — see
			// https://github.com/microsoft/playwright/issues/21133.
			//
			// `PLAYWRIGHT_KEEP_ANIMATIONS=1` opts out (debug only).
			contextOptions: process.env.PLAYWRIGHT_KEEP_ANIMATIONS
				? {}
				: {reducedMotion: 'reduce'},
		},
		// One PHP dev server per Playwright worker, each on its own port
		// starting at 8000. The 1:1 worker→server pairing — combined
		// with bumped expect/action timeouts above — replaces the
		// historical single-server + PHP_CLI_SERVER_WORKERS approach,
		// which was Unix-only (the env var is ignored on Windows
		// native).
		//
		// The launcher script `scripts/start-php-server.js` is a
		// cross-platform Node entry that:
		//   1. Seeds config.test.inc.php (idempotent — the helper in
		//      scripts/seed-test-config.js).
		//   2. Creates the per-port log dir.
		//   3. spawns `php -S 127.0.0.1:<port>` with stdout/stderr
		//      piped to temp/per-port-logs/<port>.log.
		//   4. Forwards SIGINT/SIGTERM to PHP so Playwright's webServer
		//      teardown actually stops the server.
		//
		// Doing this in Node — instead of the historical
		// `sh seed-test-config.sh && mkdir -p ... && exec php ... >>file`
		// shell command — means Windows users don't need Git Bash for
		// the seed step.
		//
		// `-d log_errors=On -d error_log=temp/per-port-logs/<port>.log`
		// (set inside start-php-server.js) doubles up the redirect: PHP
		// runtime fatals/warnings land in the same file via PHP's own
		// fopen, so even if stdio handling on some runner mangles the
		// piped streams, fatals still get captured. display_errors=Off
		// keeps errors from duplicating through stderr. memory_limit=512M
		// because publish-issue flows that fan out subscriber
		// notifications can blow past PHP's 128M default.
		webServer: phpPorts.map((port) => ({
			command: `node lib/pkp/playwright/scripts/start-php-server.js ${port}`,
			url: `http://127.0.0.1:${port}`,
			cwd: appRoot,
			// Reuse a server already listening on this port. Saves cold-boot
			// time on local iteration AND on CI re-runs where the prior
			// run's PHP servers might still be alive (e.g. a retried
			// job that didn't fully tear down). If the running server's
			// config has drifted from what this run expects, the
			// failures will surface as test errors rather than as
			// silent corruption — visible in the trace.
			reuseExistingServer: true,
			timeout: 60_000,
			// start-php-server.js pipes PHP's stdout/stderr into the
			// per-port log file directly via fs.openSync — Playwright sees
			// no useful streams from the Node launcher itself, so we
			// ignore them. This keeps `npx playwright test` output
			// readable while still preserving each server's full access
			// log in temp/per-port-logs/<port>.log for diagnosis.
			stdout: 'ignore',
			stderr: 'ignore',
			env: {
				...process.env,
				APPLICATION_ENV: 'test',
				// PHP_CLI_SERVER_WORKERS intentionally unset by
				// default. With one dedicated PHP server per
				// Playwright worker, raising the Playwright worker
				// count is the supported way to scale concurrency.
				// PHP_CLI_SERVER_WORKERS is Unix-only (Windows PHP
				// ignores it) and we want Unix and Windows to behave
				// the same so test stability investigations on Unix
				// translate directly to Windows.
				//
				// PLAYWRIGHT_PHP_WORKERS env var, if explicitly set,
				// is forwarded as PHP_CLI_SERVER_WORKERS for users
				// who want extra in-PHP concurrency on Unix as a
				// short-term workaround. Treat it as an escape hatch,
				// not the default — flakiness attributable to 1-wide
				// per-port concurrency should be addressed at the
				// test or app layer, not papered over here.
				...(process.env.PLAYWRIGHT_PHP_WORKERS
					? {PHP_CLI_SERVER_WORKERS: process.env.PLAYWRIGHT_PHP_WORKERS}
					: {}),
			},
		})),
		projects: [
			{
				name: 'setup',
				testMatch: /bootstrap\.setup\.js/,
				use: {...devices['Desktop Chrome']},
			},
			{
				name: app,
				dependencies: ['setup'],
				testMatch: [
					'playwright/tests/**/*.spec.js',
					'lib/pkp/playwright/tests/**/*.spec.js',
				],
				use: {...devices['Desktop Chrome']},
			},
		],
	});
};
