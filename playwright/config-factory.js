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
		workers: isCI ? 2 : undefined,
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
		},
		webServer: {
			// Seed config.test.inc.php from the template if missing, then
			// start php -S. The seed is a one-time cold-boot concern —
			// ConfigParser::updateConfig needs a file to read as its
			// starting point, and Application's constructor reads config
			// on first request. Subsequent runs reuse the file on disk.
			command:
				'sh -c "[ -f config.test.inc.php ] || cp config.TEMPLATE.inc.php config.test.inc.php; exec php -S 127.0.0.1:8000 -t ."',
			url: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
			cwd: appRoot,
			reuseExistingServer: !isCI,
			timeout: 60_000,
			stdout: 'pipe',
			stderr: 'pipe',
			env: {
				...process.env,
				APPLICATION_ENV: 'test',
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
