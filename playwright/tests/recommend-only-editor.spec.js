// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Playwright port of row #41 — recommend-only editor restrictions.
 *
 * Derived from AmwandengaSubmission.cy.js test 11 ("Recommend-only
 * editors can not publish, unpublish or create versions"). The Cypress
 * source toggles `recommendOnly=true` via the UI (participant edit form)
 * on a production-stage submission and drives the full "Login As" round
 * trip. Here we seed the flag declaratively via the scenario's
 * `ParticipantProcessor` (row #22 added per-participant `recommendOnly`)
 * and log in as the recommend-only user directly — the capability under
 * test is the UI gate, not the permission-toggle form.
 *
 * Feature under test: the workflow page's decision column branches on
 * each editor's `recommendOnly` flag (see
 * workflowConfigEditorialOJS.js / `currentUserCanRecommendOnly`). A
 * section editor whose StageAssignment has recommendOnly=true gets the
 * WorkflowRecommendOnlyControls panel (buttons: "Recommend Accept" /
 * "Recommend Revisions" / "Recommend Resubmit for Review" /
 * "Recommend Decline") in place of the full decision set dbarnes sees
 * ("Accept Submission" / "Request Revisions" / "Decline Submission" /
 * etc.). This spec confirms the two surfaces render for the two
 * participant types against the same submission.
 *
 * Scenario seed: an in-review submission with two editor-side
 * participants — dbarnes (full editor) + minoue (sectionEditor with
 * recommendOnly=true). Review stage is the natural home for this test
 * because Recommend* decisions are review-stage decisions (they don't
 * surface at copyediting/production).
 */
test.describe('Recommend-only editor restrictions', () => {
	test('section editor with recommendOnly=true sees recommendation buttons; full editor still sees decision buttons', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'recommend-only');
		const spec = submissionInReview({
			tag,
			participants: [
				{user: 'dbarnes', role: 'editor'},
				{user: 'minoue', role: 'sectionEditor', recommendOnly: true},
			],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		// --- Recommend-only participant: minoue ---------------------------
		// The review stage renders the WorkflowRecommendOnlyControls panel
		// for a user whose StageAssignment flips recommendOnly=true. The
		// four recommendation buttons must be visible; the full decision
		// buttons (which come from the non-recommend-only decision list)
		// must NOT.
		{
			const ctx = await asUser('minoue');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			// Wait for the workflow decision column to hydrate — the
			// decision buttons mount after the publication + stage data
			// resolves. Anchor on the presence of one expected button to
			// ride out that render.
			await expect(
				page
					.getByRole('button', {name: 'Recommend Accept', exact: true})
					.first(),
			).toBeVisible({timeout: 15_000});

			// Recommendation buttons — labels from lib/pkp/locale/en/submission.po:
			//   editor.submission.decision.recommendAccept    "Recommend Accept"
			//   editor.submission.decision.recommendRevisions "Recommend Revisions"
			//   editor.submission.decision.recommendDecline   "Recommend Decline"
			// "Recommend Resubmit for Review" requires completed reviews
			// on the round (per WorkflowStageDao decision availability),
			// so the seeded in-review round without completed reviews
			// renders only the three baseline recommend actions. Those
			// three together prove the recommend-only panel is live.
			for (const label of [
				'Recommend Accept',
				'Recommend Revisions',
				'Recommend Decline',
			]) {
				await expect(
					page.getByRole('button', {name: label, exact: true}).first(),
					`recommend-only sees ${label}`,
				).toBeVisible();
			}

			// Full decision buttons are hidden. These are the buttons the
			// deciding editor sees — the WorkflowRecommendOnlyControls
			// panel replaces (not augments) the full decision panel for
			// a recommend-only participant.
			for (const label of [
				'Accept Submission',
				'Decline Submission',
				'Request Revisions',
			]) {
				await expect(
					page.getByRole('button', {name: label, exact: true}),
					`recommend-only does not see ${label}`,
				).toHaveCount(0);
			}
		}

		// --- Full editor: dbarnes ---------------------------------------
		// Same submission, different participant surface. dbarnes lacks
		// the recommendOnly flag so renders the full decision column.
		{
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			await expect(
				page
					.getByRole('button', {name: 'Accept Submission', exact: true})
					.first(),
			).toBeVisible({timeout: 15_000});

			for (const label of [
				'Accept Submission',
				'Decline Submission',
				'Request Revisions',
			]) {
				await expect(
					page.getByRole('button', {name: label, exact: true}).first(),
					`full editor sees ${label}`,
				).toBeVisible();
			}

			// And the recommendation buttons are NOT on the full-editor
			// panel (they live inside WorkflowRecommendOnlyControls only).
			for (const label of [
				'Recommend Accept',
				'Recommend Decline',
			]) {
				await expect(
					page.getByRole('button', {name: label, exact: true}),
					`full editor does not see ${label}`,
				).toHaveCount(0);
			}
		}
	});
});

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
