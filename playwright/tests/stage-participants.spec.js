// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionDraft = require('../../../../playwright/fixtures/scenarios/submission-draft.js');

/**
 * Playwright port of the stage-participant management flow that used to
 * live inside DdioufSubmission.cy.js (copyeditor / layout editor /
 * proofreader assignments at copyediting + production stages). The
 * Cypress source mixed stage-participant assignment into a chain that
 * also drove decisions; here we isolate the capability.
 *
 * Feature under test (useParticipantManagerActions): the
 * ParticipantManager panel exposed on every workflow stage lets an
 * authorised user (manager / site admin / sub-editor) add a user to a
 * stage and later remove them. The same UI surfaces across review,
 * copyediting, and production stages — this spec exercises copyediting
 * because the Cypress source did, and because copyediting is the first
 * stage that adds a non-editor participant (copyeditor) to the default
 * cast.
 *
 * Seed shape (row 23 uses skipExternalReview to jump straight to
 * copyediting without wiring a review round): dbarnes is the seeded
 * editor, the scenario runs SkipExternalReview to land the submission on
 * WORKFLOW_STAGE_ID_EDITING=4, and the test adds `mfritz` (copyeditor)
 * as a second participant. Remove-the-participant test closes the CRUD
 * loop.
 */
test.describe('Stage-participant management', () => {
	test('editor adds a copyeditor to a copyediting-stage submission via the Participants panel', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'add-participant');
		const spec = submissionDraft({
			tag,
			participants: [{user: 'dbarnes', role: 'editor'}],
		});
		// Advance to copyediting via skipExternalReview — no review round
		// needed, landing on WORKFLOW_STAGE_ID_EDITING.
		spec.decisions = [{type: 'skipExternalReview', by: 'dbarnes'}];

		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// Participant manager panel — data-cy="participant-manager". The
		// "Assign" top button label comes from `common.assign` (see
		// lib/ui-library/src/managers/ParticipantManager/useParticipantManagerConfig.js).
		const participantManager = page.locator('[data-cy="participant-manager"]');
		await expect(participantManager).toBeVisible({timeout: 15_000});

		// Baseline: only dbarnes (editor) is seeded as a participant.
		await expect(participantManager).toContainText('Daniel Barnes');
		await expect(participantManager).not.toContainText('Maria Fritz');

		await participantManager
			.getByRole('button', {name: 'Assign', exact: true})
			.click();

		// The legacy add-participant form opens inside a reka-ui dialog.
		// The dialog title comes from `editor.submission.addStageParticipant`
		// ("Assign Participant"). Inside it sits the PHP-rendered
		// `#addParticipantForm` with the role <select>, the name search
		// input, and the user-results radio grid.
		const modal = page.getByRole('dialog', {
			name: 'Assign Participant',
			exact: true,
		});
		await expect(modal).toBeVisible({timeout: 15_000});

		const form = modal.locator('#addParticipantForm').last();
		await expect(form).toBeVisible({timeout: 15_000});

		// Pick the Copyeditor user group. The <select> id carries a
		// grid-specific suffix; the `name="filterUserGroupId"` is stable.
		// Choose by visible label which matches the default.groups.name.*
		// localization — "Copyeditor" for role=copyeditor.
		await form
			.locator('select[name="filterUserGroupId"]')
			.selectOption({label: 'Copyeditor'});

		// Type the name fragment and click Search. The Cypress helper
		// filtered down to a single row before checking the radio; do the
		// same so the radio selector is unambiguous even if the journal
		// grows more copyeditors.
		await form.locator('input[name="name"]').fill('Fritz');
		await form
			.getByRole('button', {name: 'Search', exact: true})
			.click();

		// The filtered user grid renders a radio per row with
		// name="userId"; check the one aligned with Maria Fritz. Use a
		// row-scoped locator so we don't guess at the value.
		const fritzRow = modal.locator('tr', {hasText: 'Maria Fritz'}).first();
		await expect(fritzRow).toBeVisible({timeout: 15_000});
		await fritzRow.locator('input[name="userId"]').check();

		// Submit the form. fbvFormButtons renders a default "OK" button.
		await modal
			.getByRole('button', {name: 'OK', exact: true})
			.click();

		// Modal closes; participant list re-renders with the new row.
		await expect(modal).toBeHidden({timeout: 20_000});
		await expect(participantManager).toContainText('Maria Fritz', {
			timeout: 15_000,
		});
		await expect(participantManager).toContainText('Copyeditor');
	});

	test('editor removes a stage participant via the more-actions menu', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'remove-participant');
		const spec = submissionDraft({
			tag,
			participants: [
				{user: 'dbarnes', role: 'editor'},
				// Seed mfritz directly so the remove path doesn't depend
				// on the add-path test's success.
				{user: 'mfritz', role: 'copyeditor'},
			],
		});
		spec.decisions = [{type: 'skipExternalReview', by: 'dbarnes'}];

		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		const participantManager = page.locator('[data-cy="participant-manager"]');
		await expect(participantManager).toBeVisible({timeout: 15_000});
		await expect(participantManager).toContainText('Maria Fritz');

		// The per-participant menu trigger is a DropdownActions button
		// whose label is "<fullName> More Actions" (see
		// ParticipantManager.vue — aria-label comes from the
		// `:label` prop on DropdownActions). Open the menu scoped to
		// Maria Fritz's row.
		await participantManager
			.getByRole('button', {name: 'Maria Fritz More Actions', exact: true})
			.click();

		// The menu items render as role=menuitem in the dropdown portal.
		// Click the Remove item (label = common.remove = "Remove"). Scope
		// to the page because the portal is rendered outside the
		// participant-manager subtree.
		await page
			.getByRole('menuitem', {name: 'Remove', exact: true})
			.click();

		// Confirmation dialog — reka-ui PkpDialog with data-cy="dialog"
		// and title `editor.submission.removeStageParticipant`
		// ("Remove Participant"). Click OK to confirm.
		const confirm = page.locator('[data-cy="dialog"]').filter({
			hasText: 'Remove Participant',
		});
		await expect(confirm).toBeVisible({timeout: 10_000});
		await confirm.getByRole('button', {name: /^Ok$/i}).click();

		// List re-renders without the removed participant.
		await expect(participantManager).not.toContainText('Maria Fritz', {
			timeout: 15_000,
		});
		// Sanity: the editor we kept is still there.
		await expect(participantManager).toContainText('Daniel Barnes');
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
