// @ts-check
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

/**
 * Submission wizard — language change — row #13 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/SubmissionWizard.cy.js test 5
 * (changing the submission language mid-wizard re-renders the forms in
 * the new locale). The Cypress source does several things at once:
 *
 *   (a) toggles 10 journal-level "require" / "request" flags to make
 *       every metadata field multilingual-required (row #16 territory);
 *   (b) uploads a file (row #17 territory); and
 *   (c) drives the wizard through to "Submission complete" asserting on
 *       French errors / French field labels / French metadata values.
 *
 * We keep only (c)'s re-rendering assertion — the piece that actually
 * proves the wizard changes locale — and skip (a) + (b) which belong to
 * other rows. The bootstrapped `publicknowledge` journal already
 * declares both `en` and `fr_CA` as supported submission locales
 * (playwright/fixtures/bootstrap.js), so no E0 scratch journal is
 * needed.
 *
 * Scope deviations from the Cypress source:
 *   - No journal field-config mutation. Row #16 will cover "make
 *     metadata required" as a standalone feature.
 *   - No file upload. Row #17 will cover file upload end-to-end.
 *   - No "Submission complete" assertion.
 *   - English-alongside-French inline reveal (clicking the
 *     `.pkpFormLocales__locale` secondary-locale button to expose the
 *     non-primary locale's fields) is a generic multilingual form
 *     behaviour covered by row #5 (`multilingual.spec.js`).
 *   - Categories UI-language assertion (Categories label rendering in
 *     UI locale regardless of submission locale) is covered by row
 *     #15 (`categories.spec.js`), which exercises the wizard's
 *     Categories field with submitWithCategories enabled.
 *
 * Two tests:
 *   1. Locale switch re-renders Details forms with fr_CA controls.
 *   2. Validation errors at Review re-render in the FR-locale review
 *      panel ("Details (French (Canada))" + "This field is required.")
 *      after the wizard's primary locale is fr_CA.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `wl-w${workerIndex}-${suffix}`;
}

test.use({user: 'dbarnes'});

test.describe('Submission wizard — language change', () => {
	test(
		'switching submission language mid-wizard re-renders fields in the new locale',
		{tag: '@regression'},
		async ({page}) => {
			const tag = uniqueTag();
			const title = `Language ${tag}`;

			const wizard = new SubmissionWizardPage(page);
			await wizard.goto();
			await wizard.start({title, section: 'Articles'});

			// After Begin Submission, the wizard caption reads
			// "Submitting to the Articles section in English."
			// Scope to the submission-configuration container so any
			// other copy hosting the word "English" can't match.
			const configSection = page.locator('#submission-configuration');
			await expect(configSection).toContainText(/Articles/);
			await expect(configSection).toContainText(/English/);

			// Open the reconfigure modal and pick French (Canada) +
			// Reviews. Reviews is the second seeded section on
			// publicknowledge (see playwright/fixtures/bootstrap.js);
			// picking it in the same action proves both radios re-bind
			// the caption, not just the locale one.
			await wizard.openReconfigureModal();
			await wizard.changeReconfigureSettings({
				localeLabel: 'French (Canada)',
				sectionLabel: 'Reviews',
			});

			// Caption re-renders to reflect the new submission state.
			await expect(configSection).toContainText(/Reviews/);
			await expect(configSection).toContainText(/French \(Canada\)/);

			// Step 1 is Upload Files. Advance to Details to verify the
			// metadata controls rendered under French (fr_CA) ids — the
			// wizard stores the primary form locale in step state, and
			// the Details step's Title/Abstract fields switch their
			// `control-{locale}` id suffix accordingly. This is the
			// load-bearing assertion: without it, the caption change
			// could be cosmetic.
			await wizard.continueStep();

			// The Title control for French is `titleAbstract-title-control-fr_CA`.
			// The English control (`-en`) should not be the primary /
			// initially-visible field anymore. TinyMCE's visible iframe
			// is the one whose `control-` prefix matches the current
			// step locale; assert the French iframe is present.
			await expect(
				page.locator('textarea#titleAbstract-title-control-fr_CA'),
			).toBeAttached();

			// The pkpFormLocales__locale widget — the secondary-locale
			// row at the top of a multilingual form — should show the
			// locale label "French (Canada)" highlighted as the primary.
			// Scope to the current step (Details) because subsequent
			// steps render their own copies of the widget.
			const detailsStep = page.locator(
				'.pkpStep:has(.pkpFormLocales__locale)',
			).first();
			await expect(
				detailsStep
					.locator('.pkpFormLocales__locale', {hasText: 'French (Canada)'})
					.first(),
			).toBeVisible();

			// Type a short French-locale title into the new fr_CA
			// control. The assertion that follows (the value populates
			// the backing <textarea> via editor.save()) proves the field
			// is bound to the new locale's state — not just that the
			// caption changed.
			const frenchTitle = `Titre ${tag}`;
			await wizard.setTitle(frenchTitle, 'fr_CA');

			// setTinyMceContent invokes editor.save() which mirrors the
			// editor's HTML content into the backing <textarea>. Check
			// the textarea value directly rather than reloading the
			// wizard — the wizard's `:started-steps` guard only lets the
			// user click back to steps they've completed, and a reload
			// wipes that set, so reloading and re-clicking "Details"
			// through the stepper isn't viable. The Details step's
			// autosave pipeline is covered by other specs (row #10) and
			// isn't the feature under test here; the feature is the
			// locale re-render. Checking the textarea value proves the
			// fr_CA-suffixed control is the one receiving the keystrokes.
			const titleFrTextarea = page.locator(
				'textarea#titleAbstract-title-control-fr_CA',
			);
			await expect(titleFrTextarea).toHaveValue(
				new RegExp(frenchTitle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')),
			);
		},
	);

	test(
		'review-step validation errors render under FR-locale panels when submission locale is fr_CA',
		{tag: '@regression'},
		async ({page}) => {
			const tag = uniqueTag();

			const wizard = new SubmissionWizardPage(page);
			await wizard.goto();
			// Start with a tiny title so we can clear it and reach the
			// Review step with a known required-field error. Picking
			// Articles (the section with no extra metadata gates) keeps
			// the only error path the Title field's required validation.
			await wizard.start({title: `Lang-errors ${tag}`, section: 'Articles'});

			// Switch the submission locale to fr_CA. After this, the
			// Details step's primary form locale flips, and Review
			// will render the locale-specific review panels keyed off
			// "Details (French (Canada))" / "Details (English)".
			await wizard.openReconfigureModal();
			await wizard.changeReconfigureSettings({
				localeLabel: 'French (Canada)',
			});

			// Walk to Details + clear the FR Title to force a
			// required-field error at Review. The wizard auto-seeds the
			// FR Title from the Start form's initial value because
			// reconfigure with a single supported locale sets the
			// primary form locale; clearing it gives Review a deterministic
			// "missing in fr_CA" assertion.
			await wizard.continueStep();
			await wizard.clearTitle('fr_CA');

			// Walk through Contributors + For the Editors to Review.
			// The wizard's required-field validation runs only when
			// Continue lands on Review; intermediate steps don't gate
			// missing fr_CA title. (Row #10 already exercises EN-side
			// validation; this test's value is the locale-prefixed
			// review-panel headings.)
			await wizard.continueStep(); // Details → Contributors
			await wizard.continueStep(); // Contributors → For the Editors
			await wizard.continueStep(); // For the Editors → Review

			// Top-level errors banner mirrors row #10's EN flow but in
			// the same UI locale (English; UI locale wasn't switched —
			// only the submission's primary form locale changed). The
			// per-panel review headings use the SUBMISSION locale's
			// label as the suffix, so we anchor on
			// "Details (French (Canada))".
			await expect(page.getByText(/There are one or more problems/i)).toBeVisible({
				timeout: 15_000,
			});

			const frDetailsPanel = page
				.locator('.submissionWizard__reviewPanel')
				.filter({has: page.getByRole('heading', {name: /Details \(French \(Canada\)\)/})});
			await expect(frDetailsPanel).toHaveCount(1);
			await expect(frDetailsPanel).toContainText('This field is required.');

			// Conversely, the EN-locale review panel — which exists when
			// supportedLocales includes en alongside fr_CA — must NOT
			// carry the missing-title warning, since the EN title was
			// never required (submission locale is fr_CA). Anchor by
			// asserting the EN panel does not contain the same warning
			// label. The bootstrap publicknowledge journal has
			// supportedSubmissionLocales=['en','fr_CA'], so the EN
			// panel will be present.
			const enDetailsPanel = page
				.locator('.submissionWizard__reviewPanel')
				.filter({has: page.getByRole('heading', {name: /Details \(English\)/})});
			if (await enDetailsPanel.count()) {
				await expect(enDetailsPanel.first()).not.toContainText(
					'This field is required.',
				);
			}
		},
	);
});
