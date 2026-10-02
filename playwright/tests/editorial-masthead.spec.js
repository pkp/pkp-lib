// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Editorial masthead — reader-only page built from the journal's
 * stage-participant assignments. Row #3 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/EditorialMasthead.cy.js.
 *
 * Reads the bootstrapped publicknowledge journal (no mutation) so no
 * E0 scratch journal is needed.
 */
test.describe('Editorial masthead', () => {
	test(
		'masthead page renders for anonymous readers',
		{tag: '@smoke'},
		async ({page}) => {
			const res = await page.goto(
				'/index.php/publicknowledge/en/about/editorialMasthead',
			);
			expect(res?.status()).toBe(200);
			expect(page.url()).toContain('/about/editorialMasthead');
			const h1 = page.locator('h1').first();
			await expect(h1).toBeVisible();
			await expect(h1).not.toHaveText('');
		},
	);
});
