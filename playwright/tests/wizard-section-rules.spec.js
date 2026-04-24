// @ts-check
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

/**
 * Submission wizard — section rules — row #12 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/SubmissionWizard.cy.js test 2
 * ("As an author, I am unable to submit to a section when it is marked
 * inactive or when it is configured so that only editors can submit to
 * it").
 *
 * The Cypress source was one long serial scenario: (a) flip every
 * section on publicknowledge to editor-restricted, (b) try to submit as
 * ccorino (an author) and assert "Not Allowed", (c) mark Articles
 * inactive, (d) retry as author and re-assert, (e) revert Reviews,
 * (f) submit-as-author and assert the Section legend is gone, and
 * (g) restore original state. That breadth needed a dedicated author
 * user (ccorino in Cypress) — which the Playwright baseline doesn't
 * seed (see lib/pkp/playwright/data/users.js; `rvaca` is the only other
 * journal-scoped user and he has mustChangePassword=true, `dbarnes` is
 * an editor). Recreating all four restrictive states exceeds the row's
 * scope and duplicates the admin-UI side already covered in row #8.
 *
 * Scope kept:
 *   - The wizard-side effect of `isInactive` on a section: once a
 *     section is marked inactive it's pruned from the Start form's
 *     section radio (PKPSubmissionHandler::getSubmitSections filters
 *     with ->excludeInactive()). Exercised by the single test below.
 *
 * Scope dropped:
 *   - The editor-only (editorRestricted) side of the feature. The
 *     Cypress assertion was "an author sees 'Not Allowed' / sees only
 *     one section on Start". Both shapes need a non-editor author user
 *     on the scratch journal. No such user is in the Playwright
 *     baseline today. Seeding one inside this spec would have to
 *     either add to lib/pkp/playwright/data/users.js (a cross-cutting
 *     concern worth its own change — scope creep here) or create a
 *     fresh user inline via the scenario API (no processor for that
 *     exists in E0 yet). Flag to the user: "no author-only baseline
 *     user; row #12's editor-only test dropped until one exists". The
 *     admin-UI side of editorRestricted already round-trips in row #8
 *     (playwright/tests/sections.spec.js third test).
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `wsr-w${workerIndex}-${suffix}`;
}

/**
 * Navigate to the Sections admin grid for the given journal and click
 * the grid-level "Settings" toggle so each row's Edit/Delete controls
 * are exposed. Mirrors openSectionsTab() in playwright/tests/sections.spec.js
 * — the legacy jQuery grid pattern isn't worth a shared helper for two
 * specs.
 */
async function openSectionsTab(page, journalPath) {
	await page.goto(`/index.php/${journalPath}/management/settings/context`);
	await page.locator('#sections-button').click();
	await expect(
		page.locator(
			'a[id^="component-grid-settings-sections-sectiongrid-addSection-button-"]',
		),
	).toBeVisible();
	await page.locator('#sectionsGridContainer a.show_extras').first().click();
}

async function createSection(page, {title, abbrev}) {
	await page
		.locator(
			'a[id^="component-grid-settings-sections-sectiongrid-addSection-button-"]',
		)
		.click();
	const form = page.locator('form#sectionForm');
	await expect(form).toBeVisible();
	await form.locator('input[id^="title-"]').fill(title);
	await form.locator('input[id^="abbrev-"]').fill(abbrev);
	await form.getByRole('button', {name: 'Save'}).click();
	await expect(form).toHaveCount(0, {timeout: 15_000});
	await expect(
		page.locator('tr.gridRow', {hasText: title}),
	).toBeVisible();
}

/**
 * Open the Edit dialog for the section whose title matches, tick
 * `isInactive`, and save.
 */
async function markSectionInactive(page, title) {
	const row = page.locator(
		'tr.gridRow[id^="component-grid-settings-sections-sectiongrid-row-"]',
		{hasText: title},
	);
	const rowId = await row.first().getAttribute('id');
	if (!rowId) {
		throw new Error(`Section row "${title}" not found`);
	}
	await page
		.locator(`a[id^="${rowId}-editSection-button-"]`)
		.first()
		.click();
	const form = page.locator('form#sectionForm');
	await expect(form).toBeVisible();
	await form.locator('input#isInactive').check({force: true});
	await form.getByRole('button', {name: 'Save'}).click();
	await expect(form).toHaveCount(0, {timeout: 15_000});
}

test.describe('Submission wizard — section rules', () => {
	test(
		'a section marked inactive does not appear in the wizard section picker',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();

			// E0 scratch journal. dbarnes (editor in the baseline) gets
			// manager rights here so she can both edit sections AND run
			// the wizard. That's fine for this test — the Start form's
			// section list is filtered on `excludeInactive()`
			// regardless of role (PKPSubmissionHandler.getSubmitSections).
			// Editor-only (editorRestricted) sections stay visible to
			// managers, which is why that side of the feature can't be
			// asserted with dbarnes — see the spec header.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({baseURL});
			try {
				const page = await ctx.newPage();

				// Log in via the scratch-journal login form — the
				// baseline storageState is publicknowledge-scoped.
				await page.goto(`/index.php/${context.path}/en/login`);
				await page.locator('input#username').fill('dbarnes');
				await page
					.locator('input#password')
					.fill('dbarnesdbarnes');
				await page.locator('form#login button').click();
				await page.waitForURL(
					(url) => !url.pathname.includes('/login'),
					{timeout: 15_000},
				);

				// Seed a second section so the StartSubmission form
				// renders its section radio (it's hidden when only one
				// section exists, collapsing the assertion surface).
				// With both Articles + Reviews active, both labels
				// appear — proves the baseline.
				await openSectionsTab(page, context.path);
				await createSection(page, {
					title: `Reviews ${tag}`,
					abbrev: `REV-${tag.slice(-6)}`,
				});

				// Sanity: both sections appear in the wizard picker.
				// With 2+ sections, StartSubmission renders a FieldOptions
				// radio whose <label> elements contain the section title.
				// Scope to the pkpFormField wrapper so generic "Articles"
				// copy elsewhere in the page (nav, dashboards) can't match.
				const reviewsLabel = `Reviews ${tag}`;
				const wizard = new SubmissionWizardPage(page);
				await page.goto(`/index.php/${context.path}/submission`);
				await expect(
					page.getByRole('heading', {name: 'Make a Submission'}),
				).toBeVisible();
				// Wait for the Start form Vue component to mount.
				await expect(
					page.locator('#startSubmission-title-control_ifr'),
				).toBeAttached({timeout: 15_000});
				const sectionField = page.locator(
					'.pkpFormField--options',
					{has: page.locator('legend', {hasText: 'Section'})},
				);
				await expect(sectionField).toBeVisible();
				await expect(
					sectionField.locator('label', {hasText: 'Articles'}),
				).toBeVisible();
				await expect(
					sectionField.locator('label', {hasText: reviewsLabel}),
				).toBeVisible();

				// Mark Articles inactive. Back to the Sections grid.
				await openSectionsTab(page, context.path);
				await markSectionInactive(page, 'Articles');

				// Fresh wizard visit: Articles should be gone. With
				// only Reviews active, StartSubmission passes `count ===
				// 1` to the section-count check and hides the radio
				// entirely (it adds a hidden sectionId field instead).
				// Assert both (a) no "Articles" option label in the Start
				// form and (b) no Section legend — either failure would
				// indicate the isInactive filter regressed.
				await page.goto(`/index.php/${context.path}/submission`);
				await expect(
					page.getByRole('heading', {name: 'Make a Submission'}),
				).toBeVisible();
				await expect(
					page.locator('#startSubmission-title-control_ifr'),
				).toBeAttached({timeout: 15_000});
				// The Section FieldOptions wrapper should be absent.
				await expect(
					page.locator(
						'.pkpFormField--options',
						{has: page.locator('legend', {hasText: 'Section'})},
					),
				).toHaveCount(0);
				// And no "Articles" option label anywhere in the Start
				// form (there's no other legitimate source of the word
				// on the Make-a-Submission page).
				await expect(
					page.locator('form label', {hasText: 'Articles'}),
				).toHaveCount(0);
				// And the wizard can still be started — proves we didn't
				// just render a broken form. Use the POM's start() which
				// handles the single-section case.
				await wizard.start({title: `Inactive-hide ${tag}`});
				await expect(
					page.locator('.submissionWizard'),
				).toBeVisible();
			} finally {
				await ctx.close();
			}
		},
	);
});
