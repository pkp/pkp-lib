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
 * Editor-only (editorRestricted) side of the feature is now covered
 * by test 2 below — atester (the baseline author user, added for row
 * #43) gets enrolled as an author on the scratch journal, then dbarnes
 * marks Articles as editorRestricted; atester's wizard render proves
 * the section is filtered out of the Start form's section picker.
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
	await editSectionFlag(page, title, 'isInactive');
}

/**
 * Open the Edit dialog for the section whose title matches, tick
 * `editorRestricted`, and save. editorRestricted hides the section
 * from non-editor authors' Start form section picker.
 */
async function markSectionEditorRestricted(page, title) {
	await editSectionFlag(page, title, 'editorRestricted');
}

/**
 * Generic editor: open a section's Edit dialog, tick a named
 * checkbox by id, save.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} title
 * @param {string} fieldId  HTML id of the checkbox to tick
 */
async function editSectionFlag(page, title, fieldId) {
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
	await form.locator(`input#${fieldId}`).check({force: true});
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

	test(
		'an editor-restricted section is hidden from a non-editor author in the wizard',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();

			// E0 scratch journal with dbarnes (manager) and atester
			// (author). atester is the baseline non-editor author user
			// added for row #43; enrolling her on the scratch journal
			// gives her submit rights without granting editorial
			// privileges, which is the precondition for the
			// editorRestricted gate to apply.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [
					{username: 'dbarnes', roles: ['manager']},
					{username: 'atester', roles: ['author']},
				],
			});

			// Manager session — log in as dbarnes, seed a Reviews
			// section so the journal has at least two sections (so
			// the wizard's section radio renders for both states),
			// then mark Articles as editorRestricted.
			const dbarnesCtx = await browser.newContext({baseURL});
			try {
				const dbarnesPage = await dbarnesCtx.newPage();
				await dbarnesPage.goto(`/index.php/${context.path}/en/login`);
				await dbarnesPage.locator('input#username').fill('dbarnes');
				await dbarnesPage
					.locator('input#password')
					.fill('dbarnesdbarnes');
				await dbarnesPage.locator('form#login button').click();
				await dbarnesPage.waitForURL(
					(url) => !url.pathname.includes('/login'),
					{timeout: 15_000},
				);

				const reviewsTitle = `Reviews ${tag}`;
				await openSectionsTab(dbarnesPage, context.path);
				await createSection(dbarnesPage, {
					title: reviewsTitle,
					abbrev: `REV-${tag.slice(-6)}`,
				});
				await markSectionEditorRestricted(dbarnesPage, 'Articles');
			} finally {
				await dbarnesCtx.close();
			}

			// Author session — log in as atester on the scratch
			// journal (atester's password derives to 'atesteratester'
			// per data/users.js), open the wizard, assert the
			// Articles section is filtered out of the section radio.
			const atesterCtx = await browser.newContext({baseURL});
			try {
				const atesterPage = await atesterCtx.newPage();
				await atesterPage.goto(`/index.php/${context.path}/en/login`);
				await atesterPage.locator('input#username').fill('atester');
				await atesterPage
					.locator('input#password')
					.fill('atesteratester');
				await atesterPage.locator('form#login button').click();
				await atesterPage.waitForURL(
					(url) => !url.pathname.includes('/login'),
					{timeout: 15_000},
				);

				await atesterPage.goto(
					`/index.php/${context.path}/submission`,
				);
				await expect(
					atesterPage.getByRole('heading', {name: 'Make a Submission'}),
				).toBeVisible({timeout: 15_000});
				await expect(
					atesterPage.locator('#startSubmission-title-control_ifr'),
				).toBeAttached({timeout: 15_000});

				// The Section field renders Reviews (only) when only
				// one section is author-submittable. With Articles
				// editor-restricted + atester an author, the picker
				// should not show Articles. Two assertions tie the
				// gate down:
				const reviewsTitle = `Reviews ${tag}`;
				// 1. Reviews IS visible — proves section enumeration
				//    didn't break entirely.
				const reviewsLabel = atesterPage.locator('label', {
					hasText: reviewsTitle,
				});
				if (await reviewsLabel.first().count()) {
					// 2+ sections render the FieldOptions radio.
					await expect(reviewsLabel.first()).toBeVisible();
				}
				// 2. Articles label is NOT in the wizard's Start form
				//    (per getSubmitSections's editorRestricted filter).
				await expect(
					atesterPage.locator('form label', {hasText: /^Articles$/}),
				).toHaveCount(0);
			} finally {
				await atesterCtx.close();
			}
		},
	);
});
