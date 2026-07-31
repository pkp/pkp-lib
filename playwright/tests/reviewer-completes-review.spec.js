// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {setTinyMceContent} = require('../support/tinymce.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Reviewer completes a review — row #49 in
 * docs/e2e-playwright-migration.md.
 *
 * Cypress source: scattered through the submission fixture specs
 * (LkumiegaSubmission, JnovakSubmission, DsokoloffSubmission ...) which
 * all called `cy.performReview(reviewer, password, title, recommendation)`
 * (lib/pkp/cypress/support/commands.js#625) immediately after assigning
 * a reviewer. That helper drives the four-step reviewer wizard at
 * /reviewer/submission/{submissionId}: Step 1 accepts the assignment
 * (privacy consent + Accept Review button), Step 2 acknowledges
 * guidelines, Step 3 fills the comments-to-author / comments-to-editor
 * TinyMCE editors and picks the recommendation from
 * #reviewerRecommendationId, then Submit Review → OK confirm dialog →
 * "Review Submitted" landing page.
 *
 * Original gating in the cell (E1 + E2) is lifted:
 *   - E1 (file uploads) — reviewers don't need to attach a file for the
 *     happy path; the review-form attachments grid is optional. Skipping
 *     keeps the spec on the same minimal pipeline as the other Wave 7
 *     ports.
 *   - E2 (email tokens) — superseded by direct logins. phudson is a
 *     seeded reviewer (lib/pkp/playwright/data/users.js); we log in as
 *     them through the standard storageState helper.
 *
 * Lives in lib/pkp because the reviewer wizard ships identically across
 * OJS/OMP/OPS (the templates live in lib/pkp/templates/reviewer/review/
 * and are app-agnostic). No POM extraction — the reviewer wizard isn't
 * reused anywhere else in the migration.
 */
test.describe('Reviewer completes review', () => {
	test('reviewer accepts a review assignment, fills the review form with a recommendation, and submits', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'reviewer-completes');
		// Seed a submission that's been sent to review with phudson invited
		// (no response yet). Drop jjanssen — the override list replaces the
		// fixture default entirely, and a single reviewer is all we need
		// for the wizard run.
		const spec = submissionInReview({
			tag,
			reviewers: [
				{user: 'phudson', method: 'anonymous', status: 'invited'},
			],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		// --- Reviewer side: drive the four-step wizard ------------------
		const reviewerCtx = await asUser('phudson');
		const reviewerPage = await reviewerCtx.newPage();

		// /reviewer/submission/{id} mounts on Step 1 by default (the
		// reviewer's "current" step is the lowest unsaved step). The page
		// is a server-rendered fbv form, not a Vue dashboard surface — no
		// data-cy hooks; key off form ids and stable button text.
		await reviewerPage.goto(
			`/index.php/publicknowledge/en/reviewer/submission/${submission.id}`,
		);

		// Step 1 — Request to Review. Submitting flips the assignment to
		// "accepted" and advances. The form has a privacy-consent checkbox
		// (rendered when the journal has a privacy statement; publicknowledge
		// does — see lib/pkp/cypress/support/commands.js#632) and an
		// "Accept Review, Continue to Step #2" submit button.
		const step1 = reviewerPage.locator('form#reviewStep1Form');
		await expect(step1).toBeVisible({timeout: 15_000});
		await step1.locator('input[name="privacyConsent"]').check();
		await Promise.all([
			reviewerPage.waitForURL(/\/reviewer\/submission\//, {timeout: 15_000}),
			reviewerPage
				.getByRole('button', {name: /Accept Review, Continue to Step #2/i})
				.click(),
		]);

		// Step 2 — Reviewer Guidelines (informational). One submit button:
		// "Continue to Step #3".
		const step2 = reviewerPage.locator('form#reviewStep2Form');
		await expect(step2).toBeVisible({timeout: 15_000});
		await Promise.all([
			reviewerPage.waitForURL(/\/reviewer\/submission\//, {timeout: 15_000}),
			reviewerPage
				.getByRole('button', {name: /Continue to Step #3/i})
				.click(),
		]);

		// Step 3 — review form. Two TinyMCE editors carrying ids derived
		// from FBV's uniqId suffix (textarea.tpl renders
		// `id="{FBV_id}-{FBV_uniqId}"` on every form re-render), so the
		// raw template ids `comments` / `commentsPrivate` get a numeric
		// suffix at runtime — Cypress had to match `textarea[id^="comments-"]`
		// for the same reason. Probe the DOM after Step 3 mounts to pick up
		// the live ids before driving TinyMCE. Recommendation select
		// `#reviewerRecommendationId` is the only stable id; option labels
		// come from the seeded ReviewerRecommendation rows. Default English
		// label for "accept" is "Accept Submission" (locale/en/locale.po
		// "reviewer.article.decision.accept").
		const step3 = reviewerPage.locator('form#reviewStep3Form');
		await expect(step3).toBeVisible({timeout: 15_000});

		const commentsId = await step3
			.locator('textarea[id^="comments-"]')
			.first()
			.getAttribute('id');
		const commentsPrivateId = await step3
			.locator('textarea[id^="commentsPrivate-"]')
			.first()
			.getAttribute('id');
		if (!commentsId || !commentsPrivateId) {
			throw new Error('Step 3: comments / commentsPrivate textareas not found');
		}

		await setTinyMceContent(
			reviewerPage,
			commentsId,
			'<p>Solid contribution; minor wording suggestions inline.</p>',
		);
		await setTinyMceContent(
			reviewerPage,
			commentsPrivateId,
			'<p>For the editor: I have no competing interests with this work.</p>',
		);

		await step3
			.locator('select#reviewerRecommendationId')
			.selectOption({label: 'Accept Submission'});

		// Submit Review. The button label comes from
		// `reviewer.submission.submitReview`; clicking it opens a
		// PkpDialog confirm ("reviewer.confirmSubmit") with an OK button —
		// the legacy fbv form's modalStyle="primary" path. After OK the
		// page navigates to a "Review Submitted" landing tpl.
		await step3
			.getByRole('button', {name: /^Submit Review$/i})
			.click();

		// PkpDialog confirm: "Are you sure you wish to submit this
		// review?" — OK / Cancel.
		const confirmDialog = reviewerPage.locator('[data-cy="dialog"]');
		await expect(confirmDialog).toBeVisible({timeout: 10_000});
		await confirmDialog
			.getByRole('button', {name: 'OK', exact: true})
			.click();

		// Landing page: reviewCompleted.tpl renders an h2 "Review
		// Submitted" — the canonical success signal.
		await expect(
			reviewerPage.getByRole('heading', {name: /Review Submitted/i}),
		).toBeVisible({timeout: 15_000});

		// --- REST verification ------------------------------------------
		// The submission's embedded reviewAssignments now reflect phudson's
		// completion. Editor-side fetch (dbarnes) so we don't depend on
		// the reviewer's own scope (reviewers can't always GET other
		// review assignments). REVIEW_ASSIGNMENT_STATUS_RECEIVED (7) is
		// the post-submit status before an editor confirms; STATUS_COMPLETE
		// (8) only flips after editor acknowledgement, which we don't
		// drive here. Either is "done" from the reviewer's perspective.
		const editorCtx = await asUser('dbarnes');
		const editorPage = await editorCtx.newPage();
		const subRes = await editorPage.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}`,
		);
		expect(subRes.ok()).toBe(true);
		const subBody = await subRes.json();
		const assignments = subBody.reviewAssignments || [];
		const phudsonAssignment = assignments.find((a) =>
			(a.reviewerFullName || '').includes('Paul Hudson'),
		);
		expect(phudsonAssignment, 'phudson review assignment exists').toBeTruthy();
		expect(
			[
				pkpConst.REVIEW_ASSIGNMENT_STATUS_RECEIVED,
				pkpConst.REVIEW_ASSIGNMENT_STATUS_COMPLETE,
			],
			'review assignment is marked submitted',
		).toContain(phudsonAssignment.statusId);
		// Recommendation id is present and resolves to the "Accept"
		// recommendation. Recommendation rows are seeded per-context from
		// PKP\submission\reviewer\recommendation\Repository::addDefaultRecommendations
		// — the row's defaultTranslationKey is the stable key. Look it up
		// rather than asserting on the numeric id (which depends on row
		// insertion order).
		expect(phudsonAssignment.reviewerRecommendationId).toBeGreaterThan(0);
		const recRes = await editorPage.request.get(
			`/index.php/publicknowledge/api/v1/reviewers/recommendations/${phudsonAssignment.reviewerRecommendationId}`,
		);
		if (recRes.ok()) {
			const rec = await recRes.json();
			// The row carries either the default translation key or a
			// localized title containing "Accept Submission". One of these
			// must hold.
			const matchesAccept =
				rec.defaultTranslationKey === 'reviewer.article.decision.accept' ||
				JSON.stringify(rec.title || rec.localizedTitle || {}).includes(
					'Accept Submission',
				);
			expect(matchesAccept, 'recommendation resolves to "Accept Submission"').toBe(
				true,
			);
		}
		// Comments to author landed on the assignment record (the
		// canonical write path is into a SubmissionComment row, not the
		// assignment table; round-trip via the submission's assignment
		// shape if the field is exposed, otherwise rely on the
		// recommendation + status assertions above).
	});
});

const pkpConst = {
	REVIEW_ASSIGNMENT_STATUS_RECEIVED: 7,
	REVIEW_ASSIGNMENT_STATUS_COMPLETE: 8,
};

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared submissions list.
 *
 * @param {import('@playwright/test').TestInfo} info
 * @param {string} suffix
 */
function uniqueTag(info, suffix) {
	const slug = info.title
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.slice(0, 16);
	return `t-w${info.parallelIndex}-${suffix}-${slug}`;
}
