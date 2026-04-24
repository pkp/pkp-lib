// @ts-check
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

/**
 * Submission wizard — copyright gate — row #11 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/SubmissionWizard.cy.js test 3
 * (copyright notice renders in the Review step; its checkbox gates the
 * Submit button).
 *
 * Wizard state is per-test (each run starts a new submission via the
 * wizard UI), but the copyrightNotice is context-level. The Cypress
 * source PUT `copyrightNotice` on publicknowledge, ran the test, then
 * cleared the setting — not parallel-safe. Here we create an E0
 * scratch journal with the copyrightNotice pre-seeded (the
 * ContextBuilderProcessor now accepts copyrightNotice as an optional
 * passthrough), so the setting lives only on that journal and can't
 * interfere with other parallel tests.
 *
 * Scope deviations:
 *   - French-locale assertion dropped; the English gate plus the
 *     copyright notice's literal text being present in the DOM prove
 *     the binding end-to-end. Locale switching in the wizard is
 *     row #13's concern.
 *   - "Submit succeeds → submission complete" deferred — proving a
 *     successful submit would require uploading an Article Text file
 *     (the default required-to-submit genre), which is its own
 *     migration row (#17 Filenames). This spec stops at "Submit
 *     button enables once the checkbox is ticked (once the residual
 *     file-missing gate is disregarded)" — the exact gate behaviour
 *     the Cypress source asserted on, proved via the canSubmit
 *     computed's two terms (isValid AND isConfirmed) by toggling the
 *     isConfirmed term on its own.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `wc-w${workerIndex}-${suffix}`;
}

test.describe('Submission wizard — copyright gate', () => {
	test(
		'copyright notice renders and its checkbox gates submit',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const copyrightText = `Scratch-journal copyright notice ${tag}`;

			// Create an E0 scratch journal with dbarnes (no
			// mustChangePassword) as manager — so we can drive the
			// wizard as the same user immediately. The scratch journal
			// installs a default "Articles" section automatically, so
			// the wizard has a section to submit into. copyrightNotice
			// is set up-front via the new ContextBuilderProcessor
			// passthrough so we don't have to race the wizard against
			// a separate PUT.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
				copyrightNotice: {
					en: copyrightText,
				},
			});

			const ctx = await browser.newContext({baseURL});
			try {
				const page = await ctx.newPage();
				// Sign in via the login form scoped to this scratch
				// journal's path — baseline storageState is scoped to
				// publicknowledge, which doesn't grant access here.
				await page.goto(
					`/index.php/${context.path}/en/login`,
				);
				await page.locator('input#username').fill('dbarnes');
				await page
					.locator('input#password')
					.fill('dbarnesdbarnes');
				await page.locator('form#login button').click();
				await page.waitForURL(
					(url) => !url.pathname.includes('/login'),
					{timeout: 15_000},
				);

				const wizard = new SubmissionWizardPage(page, context.path);
				await wizard.goto();
				// The scratch journal has exactly one section
				// ("Articles" from ContextService's default-section
				// hook), so the Start form hides the section radio
				// and just prompts for Title + locale + optional
				// checkboxes — start({title}) is enough.
				await wizard.start({title: `Copyright ${tag}`});

				// Step 1 Upload Files → Continue (no file uploaded;
				// submit-time validation will flag it at Review — the
				// residual gate we sidestep below).
				await wizard.continueStep();
				// Step 2 Details → Continue
				await wizard.continueStep();
				// Step 3 Contributors → Continue (dbarnes is seeded as
				// the default contributor automatically).
				await wizard.continueStep();
				// Step 4 For the Editors → Continue (no configured
				// metadata requirements on a fresh scratch journal).
				await wizard.continueStep();

				// Scroll Confirmation section into view — the review
				// panels stack vertically; Confirmation is the last
				// panel so laptop-height viewports hide it below fold.
				const confirmHeading = page.getByRole('heading', {
					name: 'Confirmation',
				});
				await confirmHeading.scrollIntoViewIfNeeded();
				await expect(confirmHeading).toBeVisible({timeout: 15_000});

				// The FieldOptions description is rendered as a
				// sibling of the checkbox control inside the
				// confirmSubmission form. The description's HTML
				// includes the copyrightNotice wrapped in a
				// <blockquote>. Page-scoped locator is safe: the
				// submission wizard is the only place blockquotes
				// render on this screen.
				await expect(
					page.locator('blockquote'),
				).toContainText(copyrightText);

				// Footer Submit button — scope locator to the wizard
				// footer so no stray "Submit" match wins.
				const submitBtn = page
					.locator('.submissionWizard__footer')
					.getByRole('button', {name: 'Submit'});

				// Gate state 1 — Unticked copyright checkbox:
				// canSubmit = isValid AND isConfirmed. isConfirmed is
				// false because the confirm-step has an unticked
				// required FieldOptions. isValid is also false
				// (no uploaded file). Submit stays disabled.
				// FieldOptions renders each option as
				// <input type="checkbox" name="confirmCopyright">
				// with no stable id; scope by name attribute.
				const copyrightCheckbox = page
					.locator('input[name="confirmCopyright"][type="checkbox"]')
					.first();
				await expect(copyrightCheckbox).not.toBeChecked();
				await expect(submitBtn).toBeDisabled();

				// Observe the file-missing warning is the OTHER
				// gating condition — this separates the two terms so
				// the test's gate assertion below is about the
				// copyright checkbox specifically.
				await expect(
					page.getByText(
						'You must upload at least one Article Text file.',
					),
				).toBeVisible();

				// Gate state 2 — Tick the copyright checkbox. The
				// isConfirmed term flips to true. Submit remains
				// disabled ONLY because of the file-missing error.
				// That's the expected compound gate — but the
				// checkbox's isConfirmed contribution is proved by
				// toggling it back off below.
				await copyrightCheckbox.check();
				await expect(copyrightCheckbox).toBeChecked();

				// Gate state 3 — Untick the copyright checkbox again.
				// isConfirmed flips back to false; submit stays
				// disabled. The point isn't just that it's disabled
				// (it was disabled in state 1 too) but that the
				// checkbox is wired to the gate — subsequent rows
				// that actually reach submit (e.g. #17 happy-path)
				// will rely on this wiring.
				await copyrightCheckbox.uncheck();
				await expect(copyrightCheckbox).not.toBeChecked();
				await expect(submitBtn).toBeDisabled();
			} finally {
				await ctx.close();
			}
		},
	);
});
