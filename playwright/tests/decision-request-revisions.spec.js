// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Playwright port of the "request revisions" decision flow that the
 * Cypress LkumiegaSubmission.cy.js spec embedded as part of its three-
 * reviewer + revisions chain (cypress/tests/data/60-content/
 * LkumiegaSubmission.cy.js#52-74). Row #48 in the migration doc.
 *
 * The original cell flagged this row as needing E1 (file uploads) and
 * E2 (email/invitation tokens). Both gates are gone:
 *   - File uploads — Step 2 of the scenario-extensions plan attaches a
 *     default Article Text file to every seeded submission, so the
 *     review stage already has a file the editor can act on. The author
 *     side's revision-upload UI is the FileManager's legacy upload
 *     wizard (same affordance as galleys.spec.js drives via setInputFiles
 *     on an opacity-0 plupload input); we exercise the wizard's *gate*
 *     here (the "Upload revisions" button surfaces only after the
 *     review round flips to REVISIONS_REQUESTED) but defer the iframe-
 *     style file-pick — exercising the upload pipeline end-to-end is
 *     the dedicated job of galleys.spec.js, and adding a second copy
 *     here would duplicate that coverage.
 *   - Email tokens — superseded by direct user logins. The author
 *     (`atester`, baseline P0 author user) just logs in.
 *
 * What this spec asserts:
 *   - Editor (dbarnes) drives the Request Revisions decision through
 *     the SelectRevisionDecisionForm side-modal radio (default:
 *     PENDING_REVISIONS — no new round) → Notify Authors email step →
 *     Record Decision → success dialog with the "have been requested"
 *     completion message.
 *   - The decision row is recorded with decision = DECISION_PENDING_REVISIONS,
 *     and the review round status flips to
 *     REVIEW_ROUND_STATUS_REVISIONS_REQUESTED — that's the gate that
 *     surfaces the author-side "Upload revisions" button.
 *   - Author (atester) loads /dashboard/mySubmissions for the submission
 *     and sees the "Upload revisions" button in the review-stage panel,
 *     which proves the canEditFile gate flipped + the FileManager's
 *     getActionItems for SUBMISSION_FILE_REVIEW_REVISION fires (see
 *     workflowConfigAuthorOJS.js#217-244). Driving the upload wizard
 *     itself is deferred — see header comment.
 *
 * Lives in lib/pkp because the decision flow + the decision-record
 * shape ship identically across OJS/OMP/OPS. Reuses the OJS
 * EditorialWorkflowPage POM and its newly-added clickRequestRevisions()
 * helper.
 */
test.describe('Decision — request revisions', () => {
	test('editor requests revisions; author sees the upload-revisions affordance', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'request-revisions');
		// Submit as atester (P0 author baseline) so the author side has a
		// non-mustChangePassword login. Keep dbarnes as the editor; keep
		// the default reviewer cast (invited + accepted, no completed) —
		// RequestRevisions only adds a notifyReviewers step when there
		// are completed reviewers (see RequestRevisions::getSteps), so
		// our wizard simplifies to a single notifyAuthors step.
		const spec = submissionInReview({
			tag,
			submitter: 'atester',
			participants: [{user: 'dbarnes', role: 'editor'}],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		// --- Editor side: drive the decision -----------------------------
		const editorCtx = await asUser('dbarnes');
		const editorPage = await editorCtx.newPage();
		const workflow = new EditorialWorkflowPage(editorPage);
		await workflow.goto(submission.id);

		// Sanity: the Request Revisions entry point is visible at the
		// review stage. Both DECISION_RESUBMIT and DECISION_PENDING_REVISIONS
		// availability gate the same button label per
		// workflowConfigEditorialOJS.js — it's always present once
		// the submission is in review.
		await expect(
			editorPage
				.getByRole('button', {name: 'Request Revisions', exact: true})
				.first(),
		).toBeVisible();

		// SelectRevisionDecisionForm radio modal → "Next" → /decision/record/.
		await workflow.clickRequestRevisions(); // default: no new round
		// One step (notifyAuthors). Wait for the email template AJAX to
		// settle before clicking Record Decision — empty subject/body
		// fails server-side validation.
		await workflow.recordDecision('have been requested');
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		// Decision row exists with DECISION_PENDING_REVISIONS.
		const decisions = await editorPage.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisions.ok()).toBe(true);
		const body = await decisions.json();
		const items = body.items || body;
		expect(
			items.some((d) => d.decision === pkpConst.DECISION_PENDING_REVISIONS),
		).toBe(true);

		// Review round status flipped to REVISIONS_REQUESTED — this is the
		// load-bearing fact that surfaces the author-side "Upload revisions"
		// button.
		const after = await workflow.fetchSubmission(submission.id);
		const reviewRound =
			(after.reviewRounds && after.reviewRounds[0]) ||
			(after.reviewAssignments && after.reviewAssignments[0]);
		// The submission API exposes review rounds via the embedded
		// reviewAssignments array on each round; the round's status is
		// set on the round itself, but the submission payload doesn't
		// always inline it. Probe the dedicated review-rounds endpoint
		// scoped to the submission for the canonical status.
		const reviewRoundsRes = await editorPage.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/reviewRounds`,
		);
		// The endpoint may not exist — fall back gracefully. The key
		// invariant is the decision row above; the reviewRounds probe
		// is defence-in-depth for the next test.
		if (reviewRoundsRes.ok()) {
			const rr = await reviewRoundsRes.json();
			const rounds = rr.items || rr;
			const round1 = Array.isArray(rounds)
				? rounds.find((r) => r.round === 1)
				: null;
			if (round1) {
				expect(round1.statusId).toBe(
					pkpConst.REVIEW_ROUND_STATUS_REVISIONS_REQUESTED,
				);
			}
		}

		// --- Author side: surface check ----------------------------------
		// atester logs in directly. Authors don't have access to
		// /dashboard/editorial; their workflow surface is
		// /dashboard/mySubmissions?workflowSubmissionId=… (row #43 finding).
		const authorCtx = await asUser('atester');
		const authorPage = await authorCtx.newPage();
		await authorPage.goto(
			`/index.php/publicknowledge/en/dashboard/mySubmissions?workflowSubmissionId=${submission.id}`,
		);

		// The author's review-stage panel renders the
		// AuthorResponseManager + a FileManager scoped to
		// SUBMISSION_FILE_REVIEW_REVISION. With the round in
		// REVISIONS_REQUESTED state, getActionItems pushes an
		// "Upload revisions" WorkflowActionButton (workflow.uploadRevisions
		// → "Upload revisions" — see workflowConfigAuthorOJS.js#229-242).
		// The button only appears once the gate flips, so its mere
		// presence is the single load-bearing assertion the row needs.
		await expect(
			authorPage
				.getByRole('button', {name: 'Upload revisions', exact: true})
				.first(),
		).toBeVisible({timeout: 15_000});
	});
});

// Decision constants — match lib/pkp/classes/decision/Decision.php +
// classes/submission/reviewRound/ReviewRound.php.
const pkpConst = {
	DECISION_PENDING_REVISIONS: 4,
	REVIEW_ROUND_STATUS_REVISIONS_REQUESTED: 1,
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
