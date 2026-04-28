// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');
const submissionPublished = require('../../../../playwright/fixtures/scenarios/submission-published.js');

/**
 * Data-availability statements — row #40 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/DataAvailabilityStatements.cy.js.
 * The Cypress source has four serial `it` blocks spread across three
 * capabilities:
 *   (1) enable the feature on the journal (Workflow → Metadata) so the
 *       publication-level field becomes editable from the workflow;
 *   (2) type the statement into the workflow's Data panel (TinyMCE
 *       editor bound to the publication's `dataAvailability` field);
 *   (3) verify anonymous/disclosed reviewers see/don't see the statement
 *       on the review-summary page.
 *
 * Scope kept — two focused tests, one per core surface:
 *   1. Manager enables "Data Availability Statement" via the journal's
 *      Workflow → Metadata settings tab and the change persists on
 *      reload. E0 scratch journal so the bootstrap publicknowledge
 *      stays read-only.
 *   2. The public article view renders the statement under its
 *      `<section id="data-availability-statement">` when the seeded
 *      publication has `dataAvailability` set. Lives on the bootstrap
 *      publicknowledge journal since the article-view render is gated
 *      only on `$publication->getLocalizedData('dataAvailability')`
 *      (see templates/frontend/objects/article_details.tpl), not on
 *      the context flag — so we can seed the metadata directly and
 *      verify the R portion without a second E0 journal.
 *
 * Scope deviations vs. Cypress source:
 *   - Dropped the "editor types into the workflow's Data panel" flow.
 *     The data-availability field is a FieldRichTextarea (TinyMCE)
 *     bound to a regular publication property. The save round-trip is
 *     already covered by row #27 (publication-metadata-editing)'s
 *     TinyMCE round-trip (title + abstract); adding a third TinyMCE
 *     round-trip for the same `edit() → Saved` path has no net-new
 *     coverage. The scenario endpoint seeds `dataAvailability` as a
 *     plain metadata entry (PublicationsProcessor already lists it in
 *     METADATA_FIELDS), which lets test (2) exercise the
 *     end-to-end "statement appears on article page" path without
 *     rebuilding the TinyMCE interaction here.
 *   - Dropped the anonymous/disclosed reviewer visibility assertions.
 *     The reviewer view test is itself gated on
 *     `Cypress.env('anonymousReviewer')` in the source (the two optional
 *     `it` blocks never execute in the shipped baseline — no such env
 *     is set), and the underlying visibility is driven by `dataAvailability`
 *     context metadata + reviewer method, a separate capability that
 *     belongs in a reviewer-view spec (row #49 — reviewer completes
 *     review) once E2 lands. This row's concrete deliverable is the
 *     manager-side toggle + the reader-side render.
 *
 * POM note: no POM; the Metadata settings tab is already driven directly
 * in playwright/tests/wizard-config-reset.spec.js, and the reader
 * assertion is a plain `locator('#data-availability-statement')`. If the
 * Metadata settings tab grows more surface, factor a shared helper into
 * lib/pkp/playwright/pages/ (shared across OJS/OMP/OPS).
 */

test.describe('Data availability statements', () => {
	test(
		'manager enables the Data Availability Statement metadata and it persists on reload',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'enable');

			// E0 scratch journal — the dataAvailability context flag is
			// journal-level configuration, and the bootstrap journal must
			// stay read-only. dbarnes is the canonical manager role.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
				const page = await ctx.newPage();

				// Navigate to the workflow settings page and activate the
				// Submission > Metadata sub-tab. Mirrors the tab navigation
				// pattern from playwright/tests/wizard-config-reset.spec.js
				// (the FieldMetadataSetting layout is shared across every
				// field on that form).
				await page.goto(
					`/index.php/${context.path}/management/settings/workflow`,
				);
				await page.locator('#submission-button').click();
				await page.locator('#metadata-button').click();

				// The Data Availability field's "Enable" checkbox label
				// comes from manager.setup.metadata.dataAvailability.enable.
				// Wait for the field to render — the fieldset carries
				// `.pkpFormField--metadata` and the unique label is the
				// ready signal.
				const enableLabel = page.locator('label', {
					hasText: 'Enable data availability statement metadata',
				});
				await expect(enableLabel).toBeVisible({timeout: 15_000});

				// Scope all subsequent interactions to the dataAvailability
				// fieldset — the Metadata form has a stack of structurally
				// identical fieldsets, and the "Enable" checkbox label is
				// the only unique anchor to this one.
				const daField = page.locator('fieldset.pkpFormField--metadata', {
					has: page.locator('label', {
						hasText: 'Enable data availability statement metadata',
					}),
				});
				await expect(daField).toBeVisible();

				// The feature is off by default on a fresh scratch journal
				// (schema default is nullable/0 — see
				// lib/pkp/schemas/context.json#dataAvailability). Tick the
				// enable checkbox, then pick the "Request" option so the
				// flag flips to `METADATA_REQUEST` (the middle of the three
				// submissionOptions — enabled without requiring input).
				const enableCheckbox = daField
					.locator('input.pkpFormField--options__input[type="checkbox"]')
					.first();
				await expect(enableCheckbox).not.toBeChecked();
				await enableCheckbox.check();

				// Clicking the label flips the radio. The locale string
				// matches manager.setup.metadata.dataAvailability.request.
				const requestLabel = daField.locator('label', {
					hasText: /Ask the author to provide a data availability statement/,
				});
				await expect(requestLabel).toBeVisible();
				await requestLabel.click();

				// Save the Metadata form. There's exactly one Save button
				// per PKP form footer. Race the click with the PUT round-
				// trip on the context endpoint — the Metadata settings
				// form posts as POST + X-Http-Method-Override: PUT; once
				// the success response lands we know the setting is
				// persisted before any navigation.
				const form = page.locator('form', {has: daField});
				await Promise.all([
					page.waitForResponse(
						(res) =>
							res.request().method() === 'POST' &&
							/\/api\/v1\/contexts\/\d+/.test(res.url()) &&
							res.ok(),
						{timeout: 15_000},
					),
					form.getByRole('button', {name: 'Save', exact: true}).click(),
				]);

				// Reload the settings page and confirm the enable checkbox
				// stays checked and the "Request" radio is selected. That's
				// the round-trip that the Cypress source ended with
				// `cy.get('#metadata [role="status"]').contains('Saved')`;
				// we anchor on the persisted state because the Metadata
				// form on this page doesn't consistently render a [role=status]
				// toast (SettingsPage notification wiring varies per form).
				await page.reload();
				await page.locator('#submission-button').click();
				await page.locator('#metadata-button').click();
				await expect(enableLabel).toBeVisible({timeout: 15_000});

				const daFieldAfterReload = page.locator(
					'fieldset.pkpFormField--metadata',
					{
						has: page.locator('label', {
							hasText: 'Enable data availability statement metadata',
						}),
					},
				);
				await expect(
					daFieldAfterReload
						.locator('input.pkpFormField--options__input[type="checkbox"]')
						.first(),
				).toBeChecked();
				// The submissionOptions radios render once the checkbox is
				// on. Pick the radio by its value attribute — the options
				// emit value="request" (Context::METADATA_REQUEST string
				// constant).
				await expect(
					daFieldAfterReload
						.locator('input[type="radio"][value="request"]')
						.first(),
				).toBeChecked();
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'published article renders the Data Availability Statement section when seeded',
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'reader');
			const statement =
				`<p>This is a seeded data availability statement (${tag}). ` +
				'Raw data supporting these findings are archived at ' +
				'https://example.org/da-archive.</p>';

			// submissionPublished + inline dataAvailability on the
			// publication. PublicationsProcessor.METADATA_FIELDS already
			// lists `dataAvailability`, so this is a plain metadata
			// passthrough into Repo::publication()->edit(). Same bootstrap
			// publicknowledge journal — the article-render gate is purely
			// `$publication->getLocalizedData('dataAvailability')` in
			// templates/frontend/objects/article_details.tpl, independent
			// of the context's dataAvailability flag (which only controls
			// editor/wizard surfaces). That means this test doesn't need
			// an E0 scratch journal, saving ~1 s of per-test setup.
			const spec = submissionPublished({tag});
			spec.publications[0].metadata = {
				...(spec.publications[0].metadata ?? {}),
				dataAvailability: {en: statement},
			};
			const {submission} = await pkpApi.createSubmission(spec);

			// Anonymous reader context — no storageState, no cookies.
			// Reduced-motion to skip any theme-side animation.
			const anon = await browser.newContext({baseURL});
			try {
				const page = await anon.newPage();
				const resp = await page.goto(
					`/index.php/publicknowledge/article/view/${submission.id}`,
				);
				expect(resp?.status()).toBe(200);

				// The default theme renders the section with
				// id="data-availability-statement" and a heading carrying
				// the localized "Data Availability Statement" label (see
				// templates/frontend/objects/article_details.tpl #403-409).
				const section = page.locator('#data-availability-statement');
				await expect(section).toBeVisible({timeout: 10_000});

				await expect(
					section.getByRole('heading', {
						name: 'Data Availability Statement',
					}),
				).toBeVisible();

				// The statement HTML is piped through |strip_unsafe_html;
				// safe-tagged content (<p>, URL text) survives. Anchor on
				// the unique `${tag}` suffix so parallel workers don't
				// confuse each other's seeded text — any pathway that
				// round-tripped *our* statement will contain the tag.
				await expect(section).toContainText(
					`This is a seeded data availability statement (${tag})`,
				);
			} finally {
				await anon.close();
			}
		},
	);
});

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared submissions list. Mirrors the helper in
 * lib/pkp/playwright/tests/oai-dc.spec.js /
 * playwright/tests/article-dc-metadata.spec.js.
 *
 * @param {import('@playwright/test').TestInfo} info
 * @param {string} suffix
 */
function uniqueTag(info, suffix) {
	const slug = info.title
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.slice(0, 16);
	// Add a short random tail so re-runs on the same DB (scratch
	// journals are keyed by path derived from the tag) don't collide
	// with the previous run's journal row.
	const rand = Math.random().toString(36).slice(2, 6);
	return `t-w${info.parallelIndex}-${suffix}-${slug}-${rand}`;
}
