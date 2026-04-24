// @ts-check
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

/**
 * Submission wizard — comments for the editor — row #14 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/SubmissionWizard.cy.js test 1 ("The
 * comments for the editor are converted to a discussion with all
 * editors and authors assigned"). The Cypress source:
 *
 *   (a) starts a wizard, uploads a dummy.pdf in the Upload Files step,
 *   (b) types a comment into the "For the Editors" step,
 *   (c) clicks Submit + confirms the modal,
 *   (d) navigates to the "Review this submission" workflow page,
 *   (e) opens the Discussion Manager and asserts that the comment
 *       text renders inside an "Comments for the Editor" discussion
 *       with Barnes (editor) + Inoue (section editor) + Corino
 *       (author/submitter) all listed as participants.
 *
 * We can't run the same flow end-to-end because step (c) is blocked on
 * step (a) — submit-time validation demands at least one Article Text
 * file, and Playwright's submission-file upload pipeline is row #17's
 * scope (scenario extension E1). The scenario API doesn't help either:
 * it bypasses Repo::submission()->submit(), which is the method that
 * calls Repo::editorialTask()->addCommentsForEditorsQuery() — so
 * seeding the submission via pkpApi + setting commentsForTheEditors
 * server-side would NOT produce the discussion the feature promises.
 * The comment→discussion mapping is triggered in one, and only one,
 * code path: wizard submit. Wizard submit needs a file. File upload is
 * blocked.
 *
 * Scope deviation:
 *   - Drop (c), (d), (e). Assert only on the parts we can prove without
 *     a file: the comment field accepts input, persists into the
 *     wizard's draft state (autosave pushes it to the submission row),
 *     and renders in the Review step's "For the Editors" review panel
 *     with the exact text back.
 *
 *   This leaves the comment→discussion wiring uncovered. That assertion
 *   moves with the first wave that can upload files — the natural home
 *   is row #17 once E1 lands. Note it in the migration doc.
 *
 * User selection:
 *   - The Cypress source uses `ccorino` (author on publicknowledge).
 *     `ccorino` isn't in the Playwright baseline users (see
 *     lib/pkp/playwright/data/users.js). `rvaca` is in the baseline but
 *     has mustChangePassword=true which diverts login. `dbarnes` is
 *     the safe substitute used by rows #10/#11. The feature — a
 *     comment typed in the wizard step binds to the submission — is
 *     role-agnostic.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `wc-w${workerIndex}-${suffix}`;
}

test.use({user: 'dbarnes'});

test.describe('Submission wizard — comments for the editor', () => {
	test(
		'comment typed on the For the Editors step persists into the wizard draft and renders in Review',
		{tag: '@regression'},
		async ({page}) => {
			const tag = uniqueTag();
			const title = `Comments ${tag}`;
			const comment =
				`Reviewer note ${tag}: please note this submission targets the ` +
				`Reviews section; prior art is listed in the abstract.`;

			const wizard = new SubmissionWizardPage(page);
			await wizard.goto();
			await wizard.start({title, section: 'Reviews'});

			// Step 1 Upload Files — skip uploading (row #17's territory);
			// the wizard flags the missing file at Review but lets earlier
			// steps advance freely.
			await wizard.continueStep();
			// Step 2 Details — Title carried over from Start; Reviews
			// section has `abstractsNotRequired: true` (bootstrap.js) so
			// Details has no hard requires. Continue.
			await wizard.continueStep();
			// Step 3 Contributors — dbarnes seeded automatically as
			// author. Continue.
			await wizard.continueStep();

			// Step 4 For the Editors — the feature under test. Type the
			// comment into the commentsForTheEditors TinyMCE.
			await wizard.setCommentsForEditors(comment);

			// Advance to Review. The comment should render inside the
			// "For the Editors" review panel with the exact text back
			// (proves both the autosave pipeline and the review-panel
			// binding).
			await wizard.continueStep();

			// Scope the assertion to the "For the Editors" review panel
			// — the review page stacks one panel per wizard step, each
			// with a heading matching its step name. Anchor by the h3
			// "For the Editors" (optional locale parenthetical) so any
			// other mentions of the comment elsewhere can't substitute.
			const forTheEditorsPanel = page
				.locator('.submissionWizard__reviewPanel')
				.filter({
					has: page.getByRole('heading', {name: /^For the Editors/}),
				});
			await expect(forTheEditorsPanel).toBeVisible({timeout: 15_000});

			// The comment text is rendered as the panel item's value. The
			// panel shows the field's localized-value HTML directly — a
			// substring match on the rendered text is enough.
			await expect(forTheEditorsPanel).toContainText(comment);

			// Sanity check: the autosave also pushed the comment into
			// the submission's `commentsForTheEditors` column server-side.
			// We verify that indirectly via the Review panel's content
			// (which useSubmission's state re-populates from the API on
			// each step transition). If autosave had not fired, the
			// review panel would render the stale (empty) value and
			// the assertion above would fail.
			//
			// The end-to-end "discussion appears on workflow page"
			// assertion is NOT made here — see the spec header for why.
			// Row #17 (file upload / E1) reopens this spec to add the
			// Submit + Discussion Manager assertions.
		},
	);
});
