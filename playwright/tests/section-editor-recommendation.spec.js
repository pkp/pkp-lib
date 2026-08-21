// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Playwright port of CcorinoSubmission.cy.js test 3 — a section editor
 * assigned with recommendOnly=true records a "Recommend Accept" inside
 * external review, and the deciding editor (dbarnes) subsequently sees
 * the recommendation on the review-stage workflow page.
 *
 * Notes on shape vs. the Cypress source:
 *   - The UI splits editor vs. recommend-only surfaces via the stage's
 *     `currentUserCanRecommendOnly` flag (see workflowConfigEditorialOJS.js
 *     L434-446). A section-editor participant whose StageAssignment has
 *     recommendOnly=true gets the WorkflowRecommendOnlyControls panel
 *     (buttons: "Recommend Revisions" / "Recommend Accept" /
 *     "Recommend Decline") in place of the full decision buttons dbarnes
 *     sees.
 *   - The scenario's ParticipantProcessor was extended (this PR) to
 *     accept `recommendOnly` / `canChangeMetadata` on participant specs
 *     so the seeded section-editor lands with recommendOnly=true without
 *     a UI round-trip.
 *   - RecommendAccept has a single email step ("Notify Editors", action
 *     id `discussion`) which auto-loads its template — awaitEmailTemplateLoaded()
 *     is required before Record Decision.
 */
test.describe('Section-editor recommendation', () => {
	test('section editor recommends Accept from within external review; assigning editor sees the recommendation', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'rec-accept');
		const spec = submissionInReview({
			tag,
			// The default in-review fixture seeds only dbarnes as editor.
			// Override participants to keep dbarnes as the deciding editor
			// AND add minoue as a recommendOnly section editor so we can
			// drive the recommendation flow.
			participants: [
				{user: 'dbarnes', role: 'editor'},
				{user: 'minoue', role: 'sectionEditor', recommendOnly: true},
			],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		// ------ Section editor (minoue): make the recommendation -----------
		{
			const ctx = await asUser('minoue');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			// A recommend-only user gets the recommendation controls panel
			// rather than the full accept/decline/etc. buttons. Verify the
			// gate ("Recommend Accept" button visible) before driving it.
			await expect(
				page
					.getByRole('button', {name: 'Recommend Accept', exact: true})
					.first(),
			).toBeVisible();

			await workflow.clickDecision('Recommend Accept');
			// Single step — Notify Editors — auto-loads its template.
			await workflow.recordDecision('Your recommendation has been recorded');
			await workflow.viewSubmissionFromCompletionDialog(submission.id);

			// API round-trip: recommendation decision row exists.
			const decisions = await page.request.get(
				`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
			);
			expect(decisions.ok()).toBe(true);
			const body = await decisions.json();
			const items = body.items || body;
			expect(
				items.some((d) => d.decision === pkpConst.DECISION_RECOMMEND_ACCEPT),
			).toBe(true);
		}

		// ------ Deciding editor (dbarnes): see the recommendation ----------
		{
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			// The review-stage secondary column renders
			// WorkflowRecommendOnlyListingRecommendations for the deciding
			// editor when at least one recommendation is recorded. It shows
			// the recommendation label(s) ("Accept Submission" for
			// RECOMMEND_ACCEPT via RecommendAccept::getRecommendationLabel).
			const recommendations = page
				.locator('[data-cy="workflow-secondary-items"]')
				.filter({hasText: 'Recommendation'});
			await expect(recommendations).toBeVisible({timeout: 15_000});
			await expect(recommendations).toContainText('Accept Submission');
		}
	});
});

// Matches lib/pkp/classes/decision/Decision.php.
const pkpConst = {
	DECISION_RECOMMEND_ACCEPT: 9,
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
