// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionDraft = require('../../../../playwright/fixtures/scenarios/submission-draft.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Playwright port of the "accept" decision assertions embedded in the
 * Cypress submission fixture specs (AmwandengaSubmission, DdioufSubmission).
 *
 * Two flavours live in one spec because they share the stage-advance
 * assertion and differ only in the pre-decision state:
 *   - stage-1 accept (skip review): SkipExternalReview,
 *     DECISION_SKIP_EXTERNAL_REVIEW=17, button "Accept and Skip Review".
 *     Steps: notifyAuthors + promoteFilesToReview.
 *   - accept-after-review:          Accept, DECISION_ACCEPT=2,
 *     button "Accept Submission". Steps: notifyAuthors (+ optional
 *     notifyReviewers — none seeded) + promoteFilesToCopyediting.
 *
 * Both land the submission on WORKFLOW_STAGE_ID_EDITING=4 (copyediting).
 * Both reuse the EditorialWorkflowPage decision helpers from row #19.
 */
test.describe('Decision — accept', () => {
	test('editor accepts a submission directly from stage 1 (accept without review)', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'accept-stage-1');
		const spec = submissionDraft({tag});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// The stage-1 "accept without review" decision lives behind the
		// "Accept and Skip Review" button (editor.submission.decision.skipReview).
		// See workflowConfigEditorialOJS.js L239-253.
		await expect(
			page
				.getByRole('button', {name: 'Accept and Skip Review', exact: true})
				.first(),
		).toBeVisible();

		await workflow.clickDecision('Accept and Skip Review');
		// SkipExternalReview has two steps (notifyAuthors + promoteFilesToReview);
		// the only email step auto-loads its template, so we need to wait
		// before Continue / Record Decision.
		await workflow.clickContinue();
		await workflow.recordDecision('skipped the review stage');
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		const after = await workflow.fetchSubmission(submission.id);
		expect(after.stageId).toBe(pkpConst.WORKFLOW_STAGE_ID_EDITING);
		expect(after.status).toBe(pkpConst.STATUS_QUEUED);

		const decisions = await page.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisions.ok()).toBe(true);
		const body = await decisions.json();
		const items = body.items || body;
		expect(
			items.some((d) => d.decision === pkpConst.DECISION_SKIP_EXTERNAL_REVIEW),
		).toBe(true);
	});

	test('editor accepts a submission after external review', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'accept-review');
		const spec = submissionInReview({tag});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// "Accept Submission" is the review-stage accept button. The two
		// seeded reviewers are in 'invited'/'accepted' states (not completed),
		// so Accept ships only the notifyAuthors step + the
		// promoteFilesToCopyediting step — no notifyReviewers step because
		// Accept::getSteps only adds it when there are REVIEW_ASSIGNMENT_COMPLETED
		// assignments.
		await expect(
			page
				.getByRole('button', {name: 'Accept Submission', exact: true})
				.first(),
		).toBeVisible();

		await workflow.clickDecision('Accept Submission');
		await workflow.clickContinue();
		await workflow.recordDecision(
			'has been accepted for publication and sent to the copyediting stage',
		);
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		const after = await workflow.fetchSubmission(submission.id);
		expect(after.stageId).toBe(pkpConst.WORKFLOW_STAGE_ID_EDITING);
		expect(after.status).toBe(pkpConst.STATUS_QUEUED);

		const decisions = await page.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisions.ok()).toBe(true);
		const body = await decisions.json();
		const items = body.items || body;
		expect(items.some((d) => d.decision === pkpConst.DECISION_ACCEPT)).toBe(
			true,
		);
	});
});

// Matches lib/pkp/classes/submission/PKPSubmission.php + Decision\Decision.php.
const pkpConst = {
	STATUS_QUEUED: 1,
	WORKFLOW_STAGE_ID_EDITING: 4,
	DECISION_ACCEPT: 2,
	DECISION_SKIP_EXTERNAL_REVIEW: 17,
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
