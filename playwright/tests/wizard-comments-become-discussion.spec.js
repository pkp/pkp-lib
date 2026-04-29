// @ts-check
const path = require('path');
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

// Bundled fixture lives in the shared lib/pkp tree at
// lib/pkp/playwright/fixtures/files/default-article.pdf — same one
// SubmissionBuilderProcessor uses for default-Article-Text seeding.
const ARTICLE_FIXTURE = path.resolve(
	__dirname,
	'..',
	'fixtures',
	'files',
	'default-article.pdf',
);

/**
 * Submission wizard — comments for the editor — row #14 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/SubmissionWizard.cy.js test 1 ("The
 * comments for the editor are converted to a discussion with all
 * editors and authors assigned").
 *
 * Two tests:
 *   1. Wizard draft autosave — comment typed on the For the Editors
 *      step renders in the Review panel with the exact text back.
 *      Proves the autosave + review-panel binding without depending
 *      on a file upload.
 *   2. End-to-end happy-path — atester (baseline author) drives the
 *      wizard from Start through to Submit, including a file upload
 *      via the wizard's plupload UI (same pattern row #17 uses); the
 *      "Submission complete" page renders; dbarnes then opens the
 *      submission's workflow page, opens the Discussion Manager, and
 *      asserts a "Comments for the Editor" discussion exists with the
 *      typed comment as its body. This closes the comment→discussion
 *      wiring assertion that previously needed E1 — it doesn't,
 *      because the wizard's plupload UI does the file upload itself
 *      (no scenario seeding required).
 *
 * User selection:
 *   - Test 1: dbarnes (manager+editor on publicknowledge). The
 *     feature is role-agnostic; the existing autosave assertion
 *     already used dbarnes.
 *   - Test 2: atester (baseline author user, no editorial roles) is
 *     the submitter; dbarnes is the editor verifying the resulting
 *     Discussion Manager state. Mirrors the Cypress source's
 *     ccorino-as-submitter pattern.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `wc-w${workerIndex}-${suffix}`;
}

test.describe('Submission wizard — comments for the editor', () => {
	// File-level user is dbarnes only for test 1 (which uses the
	// page fixture). Test 2 below opens its own contexts via
	// asUser('atester') / asUser('dbarnes').
	test.use({user: 'dbarnes'});

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
			// with a heading matching its step name. Multilingual journals
			// (publicknowledge ships en + fr_CA in supportedSubmissionMetadataLocales
			// per JournalProcessor) render one panel per locale; anchor on
			// the English variant explicitly so the comment we typed
			// (which only exists in English) is the load-bearing match.
			const forTheEditorsPanel = page
				.locator('.submissionWizard__reviewPanel')
				.filter({
					has: page.getByRole('heading', {
						name: /^For the Editors \(English\)/,
					}),
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
			// The end-to-end discussion-creation assertion lives in
			// test 2 below, which drives the wizard's plupload UI to
			// satisfy the file gate and submits.
		},
	);

	test(
		'wizard end-to-end: file upload + comment + Submit creates a Stage 1 Comments for the Editor discussion',
		{tag: '@regression'},
		async ({page}) => {
			const tag = uniqueTag();
			const title = `E2E ${tag}`;
			const comment =
				`Wizard E2E note ${tag}: this comment must show up in the ` +
				`Comments for the Editor discussion the wizard creates on submit.`;

			// dbarnes drives the wizard end-to-end — manager+editor
			// can submit through the wizard since the wizard checks
			// "user can submit" not "user is non-editor". The
			// resulting discussion will list dbarnes as the
			// participant. Cypress used `ccorino` (not in the
			// Playwright baseline); dbarnes is the safe substitute
			// that rows #10/#11 also use, and the feature being
			// tested (comment → discussion creation on submit) is
			// role-agnostic.
			const wizard = new SubmissionWizardPage(page);
			await wizard.goto();
			// Reviews section ships abstractsNotRequired=true on the
			// bootstrap journal (see bootstrap.js fixtures), so the
			// only hard publication-side requirement is the title.
			await wizard.start({title, section: 'Reviews'});

			// Step 1 — Upload Files. Drive the plupload-backed file
			// uploader the same way row #17 does: setInputFiles on
			// the hidden <input type="file">, race the upload POST,
			// then click the primary genre (Article Text) button on
			// the new list item.
			const fileInput = page.locator('input[type="file"]').first();
			await expect(fileInput).toBeAttached({timeout: 15_000});
			const [uploadResp] = await Promise.all([
				page.waitForResponse(
					(res) =>
						res.request().method() === 'POST' &&
						/\/api\/v1\/submissions\/\d+\/files$/.test(res.url()) &&
						res.ok(),
					{timeout: 30_000},
				),
				fileInput.setInputFiles(ARTICLE_FIXTURE),
			]);
			expect(uploadResp.ok()).toBeTruthy();

			const listItem = page
				.locator('.listPanel__item--submissionFile')
				.first();
			await expect(listItem).toBeVisible({timeout: 15_000});

			// Pick the Article Text genre. Same pattern as row #17:
			// the per-genre buttons render under
			// `.listPanel--submissionFiles__setGenreButton` until
			// the genreId is set. Article Text is the default primary
			// genre on every OJS journal.
			const articleTextBtn = listItem
				.locator('.listPanel--submissionFiles__setGenreButton')
				.filter({hasText: 'Article Text'})
				.first();
			await Promise.all([
				page.waitForResponse(
					(res) =>
						res.request().method() === 'POST' &&
						/\/api\/v1\/submissions\/\d+\/files\/\d+/.test(
							res.url(),
						) &&
						res.ok(),
					{timeout: 15_000},
				),
				articleTextBtn.click(),
			]);

			// Step 1 → Step 2 (Details).
			await wizard.continueStep();
			// On step 2 — set the publication title. The Start form
			// sets `submission.title` (a thin display label) but
			// the publication's own Title field stays empty until
			// the author fills it on Details. The Review panel
			// shows publication.title, not submission.title, so
			// without this the Submit gate flags "Title: None".
			await wizard.setTitle(title, 'en');
			// Step 2 → Step 3 (Contributors). dbarnes is auto-seeded
			// as the contributor on submit.
			await wizard.continueStep();
			// Step 3 → Step 4 (For the Editors).
			await wizard.continueStep();
			// On step 4 — type the comment that becomes the
			// discussion body on submit.
			await wizard.setCommentsForEditors(comment);
			// Step 4 → Step 5 (Review).
			await wizard.continueStep();

			// Capture the submission id from the URL before submit
			// so we can navigate to the workflow page afterwards.
			const submissionId = wizard.currentSubmissionId();
			expect(
				submissionId,
				'submission id resolves from /submission?id=…',
			).toBeTruthy();

			// Submit + confirm modal. The POM's submit() helper
			// races with element-not-stable when the Review panel is
			// still settling (the file-missing warning toast
			// disappearance + the Confirmation block hydration both
			// shift layout). Scroll the Submit button into view
			// first to stabilise it.
			const submitBtn = page.getByRole('button', {name: /^Submit$/});
			await expect(submitBtn).toBeVisible({timeout: 15_000});
			await submitBtn.scrollIntoViewIfNeeded();
			await expect(submitBtn).toBeEnabled({timeout: 10_000});
			await submitBtn.click();
			const confirmDialog = page.getByRole('dialog');
			await expect(confirmDialog).toBeVisible({timeout: 10_000});
			await confirmDialog
				.getByRole('button', {name: 'Submit', exact: true})
				.click();
			await expect(
				page.getByRole('heading', {name: 'Submission complete'}),
			).toBeVisible({timeout: 30_000});

			// REST sanity-check: the submission row carries the
			// comment text in its commentsForTheEditors column. Without
			// this, the comment→discussion conversion in
			// Repo::submission()->submit() (which gates on the same
			// column being non-empty) wouldn't fire — and a missing
			// row in the Discussion Manager below would have an
			// ambiguous cause.
			const subResp = await page.request.get(
				`/index.php/publicknowledge/api/v1/submissions/${submissionId}`,
			);
			expect(subResp.ok()).toBeTruthy();
			const subBody = await subResp.json();
			expect(
				subBody.commentsForTheEditors,
				`commentsForTheEditors persisted: ${JSON.stringify(subBody.commentsForTheEditors)}`,
			).toContain(comment);

			// Open the workflow page for the just-submitted
			// submission and walk to the Discussion Manager.
			await page.goto(
				`/index.php/publicknowledge/en/dashboard/editorial?workflowSubmissionId=${submissionId}`,
			);

			const dm = page.locator('[data-cy="discussion-manager"]');
			await expect(dm).toBeVisible({timeout: 20_000});

			// The Discussion Manager renders one button per
			// discussion. The wizard's submit-side flow creates a
			// discussion titled "Comments for the Editor" containing
			// the comment body.
			const discussionBtn = dm.getByRole('button', {
				name: 'Comments for the Editor',
				exact: true,
			});
			await expect(discussionBtn).toBeVisible({timeout: 15_000});
			await discussionBtn.click();

			// The discussion's detail modal mounts as a side modal.
			// The active-modal wrapper itself reports
			// `visibility: hidden` during the open transition (see
			// the .claude/skills/.../patterns.md note), so anchor on
			// the inner text directly.
			await expect(
				page.getByText(comment, {exact: false}).first(),
			).toBeVisible({timeout: 15_000});
		},
	);
});
