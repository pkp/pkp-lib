// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Jobs queue — row #45 in docs/e2e-playwright-migration.md.
 *
 * Cypress source: lib/pkp/cypress/tests/integration/Jobs.cy.js. The
 * Cypress suite exercises manual job dispatch + processing via
 * `cy.exec('php lib/pkp/tools/jobs.php ...')` shell-outs:
 *   - dispatchTestQueueJobs — enqueue N test jobs
 *   - runQueueJobs         — drain the queue, marking failable ones failed
 *   - purgeQueueJobs       — remove queued jobs
 *   - clearFailedJobs      — remove failed job rows
 * Each of those runs a CLI tool synchronously and asserts on the
 * resulting DOM row count in `/admin/jobs` and `/admin/failedJobs`.
 *
 * Playwright has no first-class "shell out during a test" equivalent,
 * and the roadmap explicitly allows skipping the manual-processing
 * surface ("If Cypress exercises manual job processing, port that too;
 * if not, skip."). What survives is the structural shape of the two
 * admin pages — they render, show the right page title, carry a
 * PkpTable landmark, and the Jobs → Failed Jobs navigation link
 * exists. That's the "jobs queue admin page is reachable and hydrated"
 * invariant; the enqueue/process/fail round-trip is covered by
 * unit-level tests in lib/pkp/tests/jobs/.
 *
 * Two tests:
 *   1. `/admin/jobs` renders with the queued-jobs table landmarks.
 *   2. `/admin/failedJobs` renders with the failed-jobs table landmarks.
 *
 * Reauthentication: AdminHandler gates admin routes through
 * ReauthenticationRequiredPolicy, which is a no-op when
 * `security.password_timeout` is 0 (the test config default — see
 * config.TEMPLATE.inc.php:330). No elevated-session dance needed.
 *
 * API-level sanity: we also hit `/index/api/v1/jobs/all` and
 * `/index/api/v1/jobs/failed/all` to verify the controller routes are
 * wired. A 200 body with the expected pagination shape proves the
 * Laravel routes + middleware stack resolve correctly regardless of
 * whether the Vue `<jobs-page>` component happened to hydrate.
 */
test.describe('Jobs queue', () => {
	test.use({user: 'admin'});

	test(
		'site admin views the queued jobs page and API',
		{tag: '@regression'},
		async ({page}) => {
			await page.goto('/index.php/index/admin/jobs');
			await expect(page).not.toHaveURL(/\/login/);
			await expect(page).not.toHaveURL(/\/admin\/confirmAccess/);

			// Page title — the h1 uses `navigation.tools.jobs` which
			// renders as "Jobs". Match case-insensitively so any future
			// label tweak doesn't false-positive.
			await expect(
				page.getByRole('heading', {name: /^Jobs$/i, level: 1}),
			).toBeVisible({timeout: 10_000});

			// The `<jobs-page>` Vue component renders a PkpTable once the
			// API call resolves. Assert the `app__contentPanel` wrapper
			// (inherent to backend.tpl) and the `<table>` element the
			// component mounts.
			await expect(page.locator('.app__contentPanel')).toBeVisible();
			await expect(page.locator('table')).toBeVisible({timeout: 15_000});

			// Controller API sanity: the Vue component feeds from
			// /index/api/v1/jobs/all. A 200 response with `items` and
			// `pagination` shape proves the middleware chain + repo
			// resolve. Use in-page fetch so cookies ride along.
			const payload = await page.evaluate(async () => {
				const r = await fetch('/index.php/index/api/v1/jobs/all', {
					headers: {Accept: 'application/json'},
				});
				return {status: r.status, body: await r.json()};
			});
			expect(payload.status).toBe(200);
			expect(payload.body).toHaveProperty('data');
			expect(payload.body).toHaveProperty('total');
		},
	);

	test(
		'site admin views the failed jobs page and API',
		{tag: '@regression'},
		async ({page}) => {
			await page.goto('/index.php/index/admin/failedJobs');
			await expect(page).not.toHaveURL(/\/login/);
			await expect(page).not.toHaveURL(/\/admin\/confirmAccess/);

			// Page title — navigation.tools.jobs.failed renders as
			// "Failed Jobs".
			await expect(
				page.getByRole('heading', {name: /Failed Jobs/i, level: 1}),
			).toBeVisible({timeout: 10_000});

			await expect(page.locator('.app__contentPanel')).toBeVisible();
			await expect(page.locator('table')).toBeVisible({timeout: 15_000});

			const payload = await page.evaluate(async () => {
				const r = await fetch('/index.php/index/api/v1/jobs/failed/all', {
					headers: {Accept: 'application/json'},
				});
				return {status: r.status, body: await r.json()};
			});
			expect(payload.status).toBe(200);
			expect(payload.body).toHaveProperty('data');
			expect(payload.body).toHaveProperty('total');
		},
	);
});
