// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionDraft = require('../../../../playwright/fixtures/scenarios/submission-draft.js');

/**
 * Playwright port of the "send to production" decision assertions
 * embedded in the Cypress submission fixture specs
 * (AmwandengaSubmission test 2, DdioufSubmission). The Cypress flow is
 *   clickDecision('Send To Production') → recordDecisionSendToProduction
 *   → isActiveStageTab('Production').
 * Here we drive the same two-button wizard (Continue → Record Decision)
 * and assert the stage flip + decision row via REST.
 *
 * SendToProduction (Decision::SEND_TO_PRODUCTION = 7) has two steps
 * (see lib/pkp/classes/decision/types/SendToProduction.php#getSteps):
 *   - notifyAuthors (email, auto-loads template — wait for the mask)
 *   - promoteFilesToProduction
 * so the interaction shape is identical to the review-stage Accept:
 *   click decision → Continue (past the email step) → Record Decision.
 *
 * Seeding: we start from a copyediting-stage submission. The simplest
 * path is `submissionDraft` + `skipExternalReview` (DECISION_SKIP_EXTERNAL_REVIEW),
 * which lands directly at WORKFLOW_STAGE_ID_EDITING=4 without needing a
 * review round. The `submissionDraft` fixture doesn't accept a
 * `decisions` override today, so we spread its output and tack on the
 * decision inline.
 */
test.describe('Decision — send to production', () => {
	test('editor sends a copyediting-stage submission to production', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'send-to-production');
		const spec = {
			...submissionDraft({tag}),
			// skipExternalReview advances stage-1 → copyediting in one
			// decision (avoids seeding a review round). See
			// DecisionProcessor::TYPE_MAP in
			// lib/pkp/classes/testing/scenario/Processor/DecisionProcessor.php.
			decisions: [{type: 'skipExternalReview', by: 'dbarnes'}],
		};
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// "Send To Production" is the copyediting-stage decision button
		// (editor.submission.decision.sendToProduction). Sanity check the
		// stage-advance preconditions are in place.
		await expect(
			page
				.getByRole('button', {name: 'Send To Production', exact: true})
				.first(),
		).toBeVisible();

		// Drive the decision wizard. SendToProduction has two steps
		// (notifyAuthors + promoteFilesToProduction), so: Continue past
		// the email step → Record Decision on the files step.
		await workflow.clickDecision('Send To Production');
		await workflow.clickContinue();
		await workflow.recordDecision('was sent to the production stage');
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		// Stage-advance assertion via REST — decoupled from the exact
		// workflow-page indicator.
		const after = await workflow.fetchSubmission(submission.id);
		expect(after.stageId).toBe(pkpConst.WORKFLOW_STAGE_ID_PRODUCTION);
		expect(after.status).toBe(pkpConst.STATUS_QUEUED);

		// Decision row recorded.
		const decisions = await page.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisions.ok()).toBe(true);
		const body = await decisions.json();
		const items = body.items || body;
		expect(
			items.some((d) => d.decision === pkpConst.DECISION_SEND_TO_PRODUCTION),
		).toBe(true);
	});
});

// Matches lib/pkp/classes/submission/PKPSubmission.php + Decision\Decision.php.
const pkpConst = {
	STATUS_QUEUED: 1,
	WORKFLOW_STAGE_ID_PRODUCTION: 5,
	DECISION_SEND_TO_PRODUCTION: 7,
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
