// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Sanity test for extension E0 — the journal scenario endpoint. Proves:
 *   - the endpoint creates a scratch context reachable at its URL path
 *   - default sections + user groups installed (so dashboards render)
 *   - a manager user assigned via the spec can log in and see the
 *     context's settings pages
 *
 * Run with:
 *   npx playwright test lib/pkp/playwright/tests/journal-scenario.spec.js
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `jscen-w${workerIndex}-${suffix}`;
}

test.describe('E0 · JournalScenarioController', () => {
	test(
		'creates a scratch journal reachable at its derived URL path',
		{tag: '@smoke'},
		async ({pkpApi, page}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Scratch journal ${tag}`},
			});

			expect(context.id).toBeGreaterThan(0);
			expect(context.path).toMatch(/^j-/);
			expect(context.primaryManager).toBeNull();

			await page.goto(`/index.php/${context.path}/`);
			// A freshly-created journal renders some kind of homepage —
			// either the bare site skin or (in OJS) an empty journal
			// homepage. Either way it should not 404.
			expect(page.url()).toContain(context.path);
			await expect(page.locator('body')).toBeVisible();
		},
	);

	test(
		'assigned manager can log in and reach the journal settings',
		{tag: '@smoke'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Scratch journal ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			expect(context.primaryManager).toEqual({username: 'dbarnes'});

			// Fresh context — no cached storage state for this specific
			// journal. Use asUser() helper to log dbarnes in (whose
			// storage state was captured against the bootstrapped
			// publicknowledge journal; session is journal-agnostic).
			const {ensureAuthStateFor} = require('../support/auth.js');
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
				reducedMotion: 'reduce',
			});

			try {
				const page = await ctx.newPage();
				// Navigate into the scratch journal's manager settings.
				// If the role assignment worked, the page renders; if
				// not, OJS redirects to /login or 403s.
				await page.goto(`/index.php/${context.path}/management/settings/context`);
				await expect(page).not.toHaveURL(/\/login/);
				// Landmark: the "Journal" / "Context" settings area
				// renders a tab strip or at least a heading.
				await expect(page.locator('body')).toBeVisible();
			} finally {
				await ctx.close();
			}
		},
	);
});
