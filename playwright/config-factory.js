// @ts-check
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
		workers: isCI ? 1 : undefined,
		reporter: isCI
			? [['github'], ['html', {open: 'never'}]]
			: [['list'], ['html', {open: 'never'}]],
		outputDir: path.join(appRoot, 'test-results'),
		timeout: 60_000,
		expect: {timeout: 10_000},
		use: {
			baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
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
		webServer: {
			// Cold-boot seed of config.test.inc.php is delegated to
			// lib/pkp/playwright/seed-test-config.sh — that script is
			// idempotent (skips if the file exists), points [email] at
			// Mailpit on 127.0.0.1:1025, and aborts with a clear error
			// if any of its sed substitutions silently no-op (e.g.
			// because config.TEMPLATE.inc.php drifted). Then start php -S.
			//
			// php -S writes its access log + worker errors to stderr;
			// we append both streams to temp/php-server.log via the shell.
			// `-d log_errors=On -d error_log=temp/php-server.log` doubles
			// up: PHP runtime fatals/warnings land in the same file via
			// PHP's own fopen, so even if Playwright's stdio handling on
			// some runner mangles the shell redirect, fatals still get
			// captured. display_errors=Off keeps errors from being
			// duplicated through stderr. The CI workflow uploads this
			// file as an artifact on failure; locally
			// `tail -f temp/php-server.log` while tests run. `temp/` is
			// in .gitignore so the file never ends up staged.
			//
			// memory_limit=512M because publish-issue flows that fan out
			// subscriber notifications can blow past PHP's 128M default.
			command:
				'sh lib/pkp/playwright/seed-test-config.sh && '
				+ 'mkdir -p temp && '
				+ 'exec php '
				+ '-d log_errors=On -d error_log=temp/php-server.log '
				+ '-d display_errors=Off '
				+ '-d memory_limit=512M '
				+ '-S 127.0.0.1:8000 -t . '
				+ '>>temp/php-server.log 2>&1',
			url: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
			cwd: appRoot,
			reuseExistingServer: !isCI,
			timeout: 60_000,
			// Shell redirection above already routes everything to the log
			// file,so Playwright sees no streams to forward. This keeps
			// `npx playwright test` output readable while still preserving
			// the dev server's access log + "Failed to poll event" noise
			// from PHP_CLI_SERVER_WORKERS in the file for diagnosis.
			stdout: 'ignore',
			stderr: 'ignore',
			env: {
				...process.env,
				APPLICATION_ENV: 'test',
				// Run php -S with multiple workers so same-origin sub-requests
				// (e.g. a page load fetching /api/...) don't deadlock the
				// single-process dev server. Unix only; ignored on Windows.
				// Bumping past 4 isn't proven to help CI stability (16 was
				// tried and CI failed identically), so we stay at the
				// historical default. If you saturate locally with a higher
				// Playwright worker count, override via the env var.
				//
				// Note: the value MUST be >= 2 (PHP rejects 1 with "number
				// of workers must be larger than 1" and silently falls back
				// to single-process mode).
				//PHP_CLI_SERVER_WORKERS: process.env.PHP_CLI_SERVER_WORKERS || '5',
			},
		},
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
