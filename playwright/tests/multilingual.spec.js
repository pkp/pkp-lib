// @ts-check
const {test, expect} = require('../support/base-test.js');
const {setTinyMceContent} = require('../support/tinymce.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
/**
 * Multilingual form fields — row #5 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/Multilingual.cy.js.
 *
 * The Cypress source is one long serial flow that:
 *   1. Deactivates fr_CA's UI-locale checkbox from the Languages grid.
 *   2. Opens the masthead form, clicks the French locale tab, types
 *      an acronym in French, saves.
 *   3. Opens an existing submission's publication Title & Abstract
 *      form, clicks French, types a title in French, saves.
 *   4. Reactivates fr_CA's UI-locale checkbox.
 *
 * Step 3 requires a pre-existing submission in the editorial workflow.
 * That pattern was brittle in Cypress (the test depended on spec-order
 * seeded state) and is out of scope for a row-#5 journal-config spec;
 * splitting it off to row #11+ / a dedicated future row is cleaner.
 * Here we port the two "pure config" assertions:
 *
 *   1. Languages grid can toggle a locale's UI-active flag and the
 *      change round-trips across reload.
 *   2. Once a locale is UI-disabled, a manager can still enter that
 *      locale's value in a multilingual form field (masthead acronym)
 *      on a forms/submissions-enabled locale, save, and the value
 *      persists on reload.
 *
 * Each test seeds its own E0 scratch journal with
 * supportedLocales=['en', 'fr_CA'] so fr_CA is installed; the
 * bootstrapped publicknowledge journal defaults to fr_CA-supported
 * too, but using a scratch journal keeps the suite immune to parallel
 * runs flipping each other's flag.
 *
 * Three tests in total — the third covers Cypress step 3
 * (submission-metadata-in-French) on a scratch journal. The audit's
 * "blocked on author-baseline seed" claim was stale: the
 * submission-in-review scenario fixture already accepts a `journal`
 * override (added for row #6's reviewer-recommendations spec), so a
 * fr_CA-supporting scratch journal can host an in-review submission
 * whose Title & Abstract panel is then driven through the FR locale
 * tab.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `mul-w${workerIndex}-${suffix}`;
}

/**
 * Click a Languages-grid checkbox and wait for the legacy AJAX
 * `save-language-setting` round-trip to complete. We avoid asserting
 * on the "Locale settings saved." toast because PKPNotificationManager
 * persists trivial notifications to the database keyed by user id; two
 * parallel tests running as the same baseline manager (dbarnes) share
 * that row pool. Whichever browser polls /notification/fetchNotification
 * first drains both notifications, so the slower test sees zero (toast
 * never appears) and the faster test sees two (strict-mode locator
 * resolves to multiple elements). Waiting on the network response is
 * deterministic and immune to that cross-talk.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').Locator} checkbox
 * @param {string} expectedSetting  e.g. 'supportedLocales' / 'supportedFormLocales'
 */
async function toggleLanguageGridCheckbox(page, checkbox, expectedSetting) {
	const saved = page.waitForResponse(
		(res) =>
			res.url().includes('save-language-setting') &&
			res.url().includes(`setting=${expectedSetting}`) &&
			res.status() === 200,
		{timeout: 15_000},
	);
	await checkbox.click();
	await saved;
}

/**
 * Open the Website settings page and navigate to Setup -> Languages.
 * The page is a Vue tab shell; Setup is a top-level tab whose panel
 * embeds the Languages sub-tab.
 */
async function openLanguagesGrid(page, journalPath) {
	await page.goto(`/index.php/${journalPath}/management/settings/website`);
	await page.locator('#setup-button').click();
	await page.getByRole('tab', {name: 'Languages'}).click();
	// The Languages grid is a legacy jQuery grid loaded via
	// load_url_in_div — wait for its content row to appear.
	await expect(
		page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
	).toBeVisible();
}

test.describe('Multilingual', () => {
	test(
		"manager toggles a locale's UI-active flag from the Languages grid",
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				supportedLocales: ['en', 'fr_CA'],
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openLanguagesGrid(page, context.path);

			const uiLocale = page.locator(
				'input[id^="select-cell-fr_CA-uiLocale"]',
			);
			// Starts checked (scratch journal seeds fr_CA as a
			// supported locale, which defaults uiLocale=on).
			await expect(uiLocale).toBeChecked();

			// Uncheck — the legacy grid binds a click handler that
			// POSTs to saveLanguageSetting over AJAX. Use .click()
			// (not .uncheck(), which asserts an immediate DOM-level
			// state flip the handler doesn't deliver) and wait for
			// the network response + the row re-render to unchecked.
			await toggleLanguageGridCheckbox(
				page,
				uiLocale,
				'supportedLocales',
			);
			await expect(
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
			).not.toBeChecked();

			// Reload the page; the flag must still be off.
			await openLanguagesGrid(page, context.path);
			await expect(
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
			).not.toBeChecked();

			// Re-enable and round-trip again.
			await toggleLanguageGridCheckbox(
				page,
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
				'supportedLocales',
			);
			await expect(
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
			).toBeChecked();

			await openLanguagesGrid(page, context.path);
			await expect(
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
			).toBeChecked();
		
		},
	);

	test(
		'manager enters French in a multilingual form field when UI locale is disabled',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				supportedLocales: ['en', 'fr_CA'],
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();

			// Disable fr_CA's UI flag via the Languages grid — the
			// form locale (used by multilingual form fields) stays
			// on by default, so the masthead form's French tab must
			// remain available. The legacy grid's click handler
			// POSTs saveLanguageSetting; we wait on the AJAX
			// response + post-re-render state, not uncheck()'s
			// DOM-level assertion.
			await openLanguagesGrid(page, context.path);
			await toggleLanguageGridCheckbox(
				page,
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
				'supportedLocales',
			);
			await expect(
				page.locator('input[id^="select-cell-fr_CA-uiLocale"]'),
			).not.toBeChecked();

			// Navigate to the Journal Settings (context) page which
			// renders the MastheadForm. FormLocales exposes French
			// as a plain <button class="pkpFormLocales__locale">;
			// its accessible name is the locale label. Scope to the
			// #masthead tab panel since the same form-locale widget
			// repeats per form on the page.
			await page.goto(
				`/index.php/${context.path}/management/settings/context`,
			);
			const masthead = page.locator('#masthead');
			await expect(masthead).toBeVisible();

			// MastheadForm has three required fields: `name`
			// (multilingual, primary locale only), `acronym`
			// (multilingual, primary locale only) and `country`.
			// A scratch journal seeds `name.en` but leaves
			// `acronym.en` empty and `country` unset, so a naive
			// French-only save would silently be blocked by
			// client-side validation. Fill the primary-locale
			// requireds first, then switch to French and type
			// the locale whose round-trip we actually care about.
			await masthead
				.locator('#masthead-acronym-control-en')
				.fill('JCP-EN');
			await masthead
				.locator('#masthead-country-control')
				.selectOption('CA');

			await masthead
				.locator('button.pkpFormLocales__locale', {hasText: 'French'})
				.first()
				.click();
			const acronymFr = masthead.locator(
				'#masthead-acronym-control-fr_CA',
			);
			await expect(acronymFr).toBeVisible();
			await acronymFr.fill('JCP');

			// The masthead form submits to
			// /index.php/{path}/api/v1/contexts/{id}. The form
			// declares PUT but Form.vue tunnels through POST with
			// X-Http-Method-Override — match on the URL, not the
			// verb. Scroll Save into view first since the tall
			// rich-text groups can push it below the viewport.
			const saveButton = masthead.getByRole('button', {
				name: 'Save',
			});
			await saveButton.scrollIntoViewIfNeeded();
			const savedResponse = page.waitForResponse(
				(res) =>
					res
						.url()
						.includes(`/api/v1/contexts/${context.id}`) &&
					res.ok(),
				{timeout: 15_000},
			);
			await saveButton.click();
			await savedResponse;

			// Reload, re-reveal French, and verify the value
			// persisted — the authoritative assertion that the
			// save round-tripped.
			await page.goto(
				`/index.php/${context.path}/management/settings/context`,
			);
			const mastheadReloaded = page.locator('#masthead');
			await expect(mastheadReloaded).toBeVisible();
			await mastheadReloaded
				.locator('button.pkpFormLocales__locale', {hasText: 'French'})
				.first()
				.click();
			await expect(
				mastheadReloaded.locator('#masthead-acronym-control-fr_CA'),
			).toHaveValue('JCP');

		},
	);

	test(
		'editor enters a French publication title on an in-review submission and it round-trips via REST',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const frenchTitle = `Titre français ${tag}`;

			// E0 scratch journal supporting both en + fr_CA. The
			// submissionInReview fixture's `journal` override targets
			// the scratch journal so the seeded submission lives there
			// (otherwise the fixture defaults to publicknowledge).
			const {context} = await pkpApi.createJournal({
				tag,
				supportedLocales: ['en', 'fr_CA'],
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const spec = submissionInReview({tag});
			spec.journal = context.path;
			const {submission} = await pkpApi.createSubmission(spec);

			// dbarnes opens the workflow page for the seeded
			// submission. Manager session is enough — Title & Abstract
			// is editable for managers/editors on draft publications.
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id, {journalPath: context.path});

			// Don't gate on the modal wrapper itself: per patterns.md
			// `[data-cy="active-modal"]` reports `visibility: hidden`
			// during the open transition. The side-nav anchor + the
			// inner heading are what we need.
			const modal = page.locator('[data-cy="active-modal"]');
			await modal
				.locator('nav a')
				.getByText('Title & Abstract', {exact: true})
				.first()
				.click();
			await expect(
				modal.getByRole('heading', {name: /Title & Abstract/}),
			).toBeVisible({timeout: 20_000});

			// Click the French (Canada) locale tab on the form's
			// pkpFormLocales control. This switches the form's
			// primary locale; subsequent setTinyMceContent on the
			// FR control id targets the right field.
			await modal
				.locator('button.pkpFormLocales__locale', {hasText: 'French'})
				.first()
				.click();

			// Set the FR title via TinyMCE. The control id pattern
			// is `titleAbstract-title-control-{locale}`.
			await setTinyMceContent(
				page,
				'titleAbstract-title-control-fr_CA',
				frenchTitle,
			);

			// Save the form (race the publication PUT). Title &
			// Abstract posts to the publication endpoint — same
			// pattern row #27's working save uses.
			await Promise.all([
				page.waitForResponse(
					(res) =>
						/\/api\/v1\/submissions\/\d+\/publications\/\d+/.test(
							res.url(),
						) &&
						res.ok() &&
						['POST', 'PUT'].includes(res.request().method()),
					{timeout: 20_000},
				),
				modal
					.getByRole('button', {name: 'Save', exact: true})
					.click(),
			]);

			// REST round-trip: fetch the publication and assert
			// title.fr_CA carries the seeded French value.
			const subResp = await page.request.get(
				`/index.php/${context.path}/api/v1/submissions/${submission.id}`,
			);
			expect(subResp.ok()).toBeTruthy();
			const subBody = await subResp.json();
			const pubResp = await page.request.get(
				`/index.php/${context.path}/api/v1/submissions/${submission.id}/publications/${subBody.currentPublicationId}`,
			);
			expect(pubResp.ok()).toBeTruthy();
			const pub = await pubResp.json();
			expect(pub.title?.fr_CA).toContain(frenchTitle);
		},
	);
});
