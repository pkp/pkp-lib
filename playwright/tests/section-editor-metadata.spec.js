// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionPublished = require('../../../../playwright/fixtures/scenarios/submission-published.js');

/**
 * Playwright port of row #42 — section-editor metadata permissions.
 *
 * Derived from AmwandengaSubmission.cy.js test 12 ("Section editors
 * can have their permission to edit publication data revoked"). The
 * Cypress source drives the full `Login As` round-trip after toggling
 * canChangeMetadata via the participant edit form on a production-stage
 * submission; here we seed both variants declaratively via the
 * scenario's `ParticipantProcessor` (row #22 added per-participant
 * `canChangeMetadata` support) and log in as the section editor
 * directly — the capability under test is the UI gate, not the
 * toggle form.
 *
 * Feature under test: section editors whose StageAssignment has
 * canChangeMetadata=false render the Publication panels in read-only
 * mode. The canonical surfacing is a disabled Save button on the
 * Title & Abstract panel (see useWorkflowConfigEditorialOJS /
 * WorkflowPublicationEditDisabled). With canChangeMetadata=true (or
 * unset — the default), the same user sees an enabled Save button and
 * the "Saved" toast after a save round-trip.
 *
 * Seed: `submissionPublished({participants: [...]})` with minoue as a
 * section-editor participant. The published fixture runs decisions as
 * `editor` (dbarnes, default) — dbarnes must remain in the participant
 * list because the decision chain (sendExternalReview → accept →
 * sendToProduction) is made by a participant-as-editor. Tests then
 * log in as minoue (the section editor) and probe the Publication
 * pane's Save affordance.
 */
test.describe('Section-editor metadata permissions', () => {
	test('section editor with canChangeMetadata=false sees a disabled Save on Title & Abstract', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'cm-false');
		const spec = submissionPublished({
			tag,
			participants: [
				{user: 'dbarnes', role: 'editor'},
				{user: 'minoue', role: 'sectionEditor', canChangeMetadata: false},
			],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('minoue');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// Open the Title & Abstract panel on the published v1. The
		// side-nav link opens the shared titleAbstract form; the
		// canChangeMetadata gate disables the Save button on submit.
		await workflow.openPublicationPanel('Title & Abstract');

		// Save button renders but is disabled for this user. Anchor on
		// the workflow modal to avoid picking up Save buttons from
		// stacked dialogs (e.g. the publish side-modal).
		const saveButton = workflow
			.workflowModal()
			.getByRole('button', {name: 'Save', exact: true})
			.first();
		await expect(saveButton).toBeVisible({timeout: 15_000});
		await expect(saveButton).toBeDisabled({timeout: 15_000});
	});

	test('section editor with canChangeMetadata=true can Save Title & Abstract', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'cm-true');
		const spec = submissionPublished({
			tag,
			participants: [
				{user: 'dbarnes', role: 'editor'},
				// canChangeMetadata defaults to true in the stage-assignment
				// table when the scenario doesn't pass it; set it
				// explicitly here for intent. `recommendOnly` stays false
				// so this participant surfaces the full Save control.
				{
					user: 'minoue',
					role: 'sectionEditor',
					canChangeMetadata: true,
				},
			],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('minoue');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		await workflow.openPublicationPanel('Title & Abstract');

		// With canChangeMetadata=true the Save button is enabled.
		const saveButton = workflow
			.workflowModal()
			.getByRole('button', {name: 'Save', exact: true})
			.first();
		await expect(saveButton).toBeVisible({timeout: 15_000});
		await expect(saveButton).toBeEnabled({timeout: 15_000});
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
