// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Review-round lifecycle — row #50 in the migration doc.
 *
 * Cypress source: the chain embedded in cypress/tests/data/60-content/
 * AmwandengaSubmission and LkumiegaSubmission specs — request revisions
 * on round 1, then open a new external review round.
 *
 * The original cell flagged this row as needing E3 (RevisionRoundProcessor).
 * That extension shipped — see playwright/fixtures/scenarios/
 * submission-in-round-2.js + the ReviewRoundProcessor wiring under
 * lib/pkp/api/v1/_test/. The "is the multi-round seeding correct"
 * invariant is owned by the fixture-sanity spec at playwright/tests/
 * scenarios/submission-in-round-2.spec.js (it reads back the chain
 * through the submission API + workflow page after seeding). What this
 * spec adds on top is the *UI-driven* equivalent: an editor logs in to
 * a single-round submission and uses the workflow page to (a) request
 * revisions on round 1 and (b) open round 2 via the
 * "Create New Review Round" decision. That's the load-bearing path
 * the row originally listed in the Tests cell.
 *
 * Test 2 from the row's plan ("seeded round-2 state surfaces correctly")
 * is intentionally deferred — its assertions overlap entirely with
 * playwright/tests/scenarios/submission-in-round-2.spec.js. Rebuilding
 * it here would duplicate coverage without strengthening any guarantee.
 *
 * Lives in lib/pkp because the decision/review-round wiring is shared
 * across OJS/OMP/OPS. Reuses EditorialWorkflowPage (OJS-only POM today;
 * sibling apps will import their own once they adopt the scenario API).
 */
test.describe('Review-round lifecycle', () => {
	test('editor closes round 1 with revisions and opens round 2 from the workflow UI', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'round-lifecycle');
		// Round 1 needs at least one *completed* reviewer for the
		// requestRevisions decision to mark "this round is done" and
		// for newExternalRound to surface as an available decision.
		// The default submissionInReview reviewers are invited/accepted —
		// we override here with a single completed reviewer to avoid
		// the hidden gate.
		const spec = submissionInReview({
			tag,
			participants: [{user: 'dbarnes', role: 'editor'}],
			reviewers: [
				{
					user: 'phudson',
					method: 'anonymous',
					status: 'completed',
					recommendation: 'pendingRevisions',
				},
			],
		});
		const {submission, reviewRounds} = await pkpApi.createSubmission(spec);
		const round1Id = reviewRounds[0].roundId;

		// --- Editor side -------------------------------------------------
		const editorCtx = await asUser('dbarnes');
		const page = await editorCtx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// 1) Request Revisions on round 1.
		// With one completed reviewer, RequestRevisions::getSteps adds a
		// notifyReviewers step on top of notifyAuthors → the wizard has
		// two steps (Continue → Record Decision).
		await workflow.clickRequestRevisions(); // default: PENDING_REVISIONS
		await workflow.clickContinue(); // notifyAuthors → notifyReviewers
		await workflow.recordDecision('have been requested');
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		// 2) Now that round 1 is closed with revisions requested,
		// "Create New Review Round" is available
		// (editor.submission.createNewRound — see
		// workflowConfigEditorialOJS.js for the gating). Click it; the
		// decision wizard has one notifyAuthors step.
		await expect(
			page
				.getByRole('button', {name: 'Create New Review Round', exact: true})
				.first(),
		).toBeVisible({timeout: 15_000});
		await workflow.clickDecision('Create New Review Round');
		// NewExternalReviewRound has two steps: notifyAuthors + a
		// PromoteFiles step ("Select Files"). Continue past the email
		// step before recording.
		await workflow.clickContinue();
		await workflow.recordDecision('A new round of review has been created');
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		// --- Assertions --------------------------------------------------
		// Decision history records both decisions, in order.
		const decisionsRes = await page.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisionsRes.ok()).toBe(true);
		const decisionsBody = await decisionsRes.json();
		const items = decisionsBody.items || decisionsBody;
		expect(
			items.some((d) => d.decision === pkpConst.DECISION_PENDING_REVISIONS),
			'requestRevisions decision row should exist',
		).toBe(true);
		expect(
			items.some((d) => d.decision === pkpConst.DECISION_NEW_EXTERNAL_ROUND),
			'newExternalRound decision row should exist',
		).toBe(true);

		// A second review round exists for stage 3, distinct from round 1.
		// The submission GET embeds reviewAssignments scoped to the latest
		// round; what we want is the dedicated reviewRounds endpoint to
		// confirm the round count.
		const rrRes = await page.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/reviewRounds`,
		);
		if (rrRes.ok()) {
			const rrBody = await rrRes.json();
			const rounds = rrBody.items || rrBody;
			// Filter to stage 3 (external review) for safety.
			const stage3Rounds = Array.isArray(rounds)
				? rounds.filter((r) => r.stageId === 3)
				: [];
			expect(stage3Rounds).toHaveLength(2);
			const round2 = stage3Rounds.find((r) => r.round === 2);
			expect(round2, 'round 2 should exist after newExternalRound').toBeTruthy();
			expect(round2.id).not.toBe(round1Id);
		} else {
			// Fall back to the submission shape — its currentReviewRound
			// indicator should now point at round 2. The decision-row
			// assertion above is the load-bearing fact; this is
			// defence-in-depth for environments that gate
			// /reviewRounds behind a permission we don't have here.
			const sub = await workflow.fetchSubmission(submission.id);
			expect(sub.currentReviewRoundId, 'currentReviewRoundId moved past round 1').not.toBe(
				round1Id,
			);
		}
	});
});

const pkpConst = {
	DECISION_PENDING_REVISIONS: 4,
	DECISION_NEW_EXTERNAL_ROUND: 14,
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
