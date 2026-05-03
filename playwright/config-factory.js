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
		timeout: 60_000,
		expect: {timeout: 10_000},
		use: {
			// Default points at the first PHP server (port 8000) — used
			// by the setup project (which runs single-worker on
			// parallelIndex=0) and as a fallback. The main project
			// overrides this per-worker via the baseURL fixture in
			// lib/pkp/playwright/support/base-test.js so each parallel
			// worker hits its own dedicated PHP server.
			baseURL: process.env.PLAYWRIGHT_BASE_URL || `http://127.0.0.1:${phpPorts[0]}`,
			actionTimeout: 10_000,
			navigationTimeout: 30_000,
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
		// starting at 8000. The 1:1 worker→server pairing replaces the
		// historical PHP_CLI_SERVER_WORKERS approach, which is Unix-only
		// (the env var is ignored on Windows native — single-process
		// php -S then deadlocks on same-origin sub-requests).
		//
		// Cold-boot seed of config.test.inc.php is delegated to
		// lib/pkp/playwright/seed-test-config.sh. The script is idempotent
		// (line 13: `if [ -f config.test.inc.php ]; then exit 0; fi`), so
		// running it from every webServer entry concurrently is safe — only
		// the first invocation does work; subsequent ones short-circuit.
		// The script aborts with a clear error if any of its sed
		// substitutions silently no-op (e.g. because config.TEMPLATE.inc.php
		// drifted) so the failure is "webServer didn't start" instead of
		// "Mailpit asserts time out 10 minutes later".
		//
		// All servers append their access log + worker errors to a single
		// temp/php-server.log. PHP prefixes each line with [PID], so
		// per-server output remains greppable. `-d log_errors=On
		// -d error_log=temp/php-server.log` doubles up: PHP runtime
		// fatals/warnings land in the same file via PHP's own fopen,
		// so even if Playwright's stdio handling mangles the shell
		// redirect, fatals still get captured. display_errors=Off
		// keeps errors from being duplicated through stderr. The CI
		// workflow uploads this file as an artifact on failure; locally
		// `tail -f temp/php-server.log` while tests run. `temp/` is in
		// .gitignore.
		//
		// memory_limit=512M because publish-issue flows that fan out
		// subscriber notifications can blow past PHP's 128M default.
		webServer: phpPorts.map((port) => ({
			command:
				'sh lib/pkp/playwright/seed-test-config.sh && '
				+ 'mkdir -p temp && '
				+ 'exec php '
				+ '-d log_errors=On -d error_log=temp/php-server.log '
				+ '-d display_errors=Off '
				+ '-d memory_limit=512M '
				+ `-S 127.0.0.1:${port} -t . `
				+ '>>temp/php-server.log 2>&1',
			url: `http://127.0.0.1:${port}`,
			cwd: appRoot,
			reuseExistingServer: !isCI,
			timeout: 60_000,
			// Shell redirection above already routes everything to the log
			// file, so Playwright sees no streams to forward. This keeps
			// `npx playwright test` output readable while still preserving
			// the dev server's access log in the file for diagnosis.
			stdout: 'ignore',
			stderr: 'ignore',
			env: {
				...process.env,
				APPLICATION_ENV: 'test',
				// PHP_CLI_SERVER_WORKERS is intentionally NOT set — each
				// Playwright worker has its own dedicated PHP server on a
				// unique port, so no in-PHP parallelism is needed.
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
