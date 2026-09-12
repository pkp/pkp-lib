// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionDraft = require('../../../../playwright/fixtures/scenarios/submission-draft.js');

/**
 * Playwright port of the "send to external review" decision assertion
 * that used to sit embedded inside the Cypress submission fixture specs
 * (AmwandengaSubmission / CcorinoSubmission / DdioufSubmission). Each
 * test owns its submission end-to-end via the scenario endpoint.
 *
 * The Cypress legacy commands chained email-template + promote-files
 * panels (`recordDecisionSendToReview` in lib/pkp/cypress/support/commands.js);
 * here we accept the server's baked-in defaults and only interact with
 * the two wizard buttons (Continue → Record Decision). The interesting
 * feature is the decision itself and the stage advance, not the email
 * UI — those get their own coverage elsewhere once we need it.
 *
 * POM note: the spec lives in lib/pkp because the decision flow ships
 * identically across OJS/OMP/OPS. The workflow-page POM it drives is
 * OJS-only (playwright/pages/EditorialWorkflowPage.js); OMP/OPS will
 * import their own sibling once those apps adopt the scenario endpoint.
 */
test.describe('Decision — send to external review', () => {
	test('editor sends a stage-1 submission to external review', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'send-to-review');
		const spec = submissionDraft({tag});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// Before the decision, the submission is in stage 1 (SUBMISSION).
		// Confirm the Send for Review entry point is visible — it's both
		// a sanity check on the scenario state and on our translation
		// label (the Cypress suite hardcoded "Send for Review").
		await expect(
			page.getByRole('button', {name: 'Send for Review', exact: true}).first(),
		).toBeVisible();

		// Drive the decision wizard. SendExternalReview has two steps:
		// notifyAuthors (Continue) + promoteFilesToReview (Record Decision).
		await workflow.clickDecision('Send for Review');
		await workflow.clickContinue();
		await workflow.recordDecision('has been sent to the review stage');
		await workflow.viewSubmissionFromCompletionDialog(submission.id);

		// Stage-advance assertion via the API — decoupled from the exact
		// shape of the workflow-page indicator so it survives UI reshuffles.
		const after = await workflow.fetchSubmission(submission.id);
		expect(after.stageId).toBe(pkpConst.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
		expect(after.status).toBe(pkpConst.STATUS_QUEUED);

		// A decision row exists for this submission.
		const decisions = await page.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisions.ok()).toBe(true);
		const decisionsBody = await decisions.json();
		const items = decisionsBody.items || decisionsBody;
		expect(
			items.some((d) => d.decision === pkpConst.DECISION_EXTERNAL_REVIEW),
		).toBe(true);
	});
});

// Matches lib/pkp/classes/submission/PKPSubmission.php + Decision\Decision.php.
const pkpConst = {
	STATUS_QUEUED: 1,
	WORKFLOW_STAGE_ID_EXTERNAL_REVIEW: 3,
	DECISION_EXTERNAL_REVIEW: 3, // Decision::EXTERNAL_REVIEW
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
