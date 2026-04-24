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
 *   - No "Submission complete" assertion. The assertion here is the
 *     forms re-mount with French locale controls — the UI contract
 *     the feature is actually about.
 *   - No English-alongside-French assertion (clicking the
 *     `.pkpFormLocales__locale` secondary-locale button to reveal the
 *     non-primary locale's fields inline). That's a generic multilingual
 *     form behaviour already covered by row #5 (`multilingual.spec.js`).
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
});
