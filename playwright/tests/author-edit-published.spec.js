// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionDraft = require('../../../../playwright/fixtures/scenarios/submission-draft.js');

/**
 * Row #43 — Author edit-publication permission gate.
 *
 * Cypress source: AmwandengaSubmission.cy.js tests 4–5 ("Author can not
 * edit publication details" / "Allow author to edit publication
 * details"). The Cypress flow runs against an unpublished stage-1
 * submission whose submitter is `amwandenga` (an author-only user
 * registered inside Cypress test 1 via `cy.register`). Test 4 asserts
 * the canChangeMetadata=false default disables the Title & Abstract
 * Save button for the author; test 5 toggles canChangeMetadata=true on
 * the author's StageAssignment and re-asserts that Save becomes
 * actionable.
 *
 * Playwright port deviations:
 *   - Author-only user `atester` (added to the baseline alongside this
 *     spec — see lib/pkp/playwright/data/users.js) replaces Cypress's
 *     ad-hoc `amwandenga`. atester is publicknowledge-scoped, role
 *     ['author'] only, mustChangePassword=false → password derives to
 *     'atesteratester' via getPassword.
 *   - We seed a draft submission (submission-draft fixture, status =
 *     STATUS_QUEUED, no decisions, no publish) — same shape Cypress
 *     test 4 actually runs against. The deferred file's earlier note
 *     about "after the editor publishes" was inaccurate — Cypress test 6
 *     publishes only after these author-gate tests have already run.
 *   - Test 1 uses atester as the submitter, so SubmissionBuilder's
 *     auto-author StageAssignment lands with canChangeMetadata =
 *     UserGroup.permitMetadataEdit (false for the default Author group
 *     in registry/userGroups.xml) — exactly the disabled-Save default
 *     the Cypress test depends on.
 *   - Test 2 uses rvaca as the submitter and adds atester explicitly via
 *     `participants` with `canChangeMetadata: true`. Why a different
 *     submitter: SubmissionBuilder auto-creates an Author StageAssignment
 *     for the submitter with default flags, and the scenario
 *     ParticipantProcessor's `Repo::stageAssignment()->build()` is a
 *     `firstOr` — it returns an existing matching assignment without
 *     applying the spec's canChangeMetadata flag. Routing the submitter
 *     through rvaca lets atester's first stage assignment come from the
 *     spec, with canChangeMetadata=true preserved end-to-end.
 *
 * Gate under test: Repo::submission()->canEditPublication is the single
 * source of truth used by the Vue workflow store
 * (useWorkflowConfigEditorialOJS) to disable Save on the publication
 * panels. With an Author-only stage assignment + canChangeMetadata=false
 * the gate returns false → disabled Save. With canChangeMetadata=true on
 * the same Author assignment the gate returns true (and the
 * hasLockedPublication branch doesn't fire because the publication is
 * STATUS_QUEUED, not published).
 */
test.describe('Author edit-publication permission', () => {
	test('author with canChangeMetadata=false sees a disabled Save on Title & Abstract', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'cm-false');
		const spec = submissionDraft({
			tag,
			submitter: 'atester',
			// One editor in the participant list so the workflow has the
			// usual editor present (some workflow store assertions assume
			// at least one non-author participant exists). atester's
			// Author stage assignment is created by SubmissionBuilder
			// from the submitter and inherits the default UserGroup
			// permitMetadataEdit=false.
			participants: [{user: 'dbarnes', role: 'editor'}],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('atester');
		const page = await ctx.newPage();
		// Authors don't have access to /dashboard/editorial — that returns
		// "The current role does not have access to this operation." Their
		// equivalent landing is /dashboard/mySubmissions, which exposes the
		// same submission-modal workflow surface (the side-nav, the
		// Publication panels) gated by canEditPublication. Mirrors the
		// Cypress flow at AmwandengaSubmission.cy.js:415.
		await page.goto(
			`/index.php/publicknowledge/en/dashboard/mySubmissions?workflowSubmissionId=${submission.id}`,
		);
		const workflow = new EditorialWorkflowPage(page);

		await workflow.openPublicationPanel('Title & Abstract');

		// Save button renders inside the workflow modal but is disabled
		// for an Author-only user with canChangeMetadata=false. Anchor on
		// the workflow modal to avoid catching Save buttons from any
		// stacked dialog.
		const saveButton = workflow
			.workflowModal()
			.getByRole('button', {name: 'Save', exact: true})
			.first();
		await expect(saveButton).toBeVisible({timeout: 15_000});
		await expect(saveButton).toBeDisabled({timeout: 15_000});
	});

	test('author with canChangeMetadata=true can Save Title & Abstract', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'cm-true');
		const spec = submissionDraft({
			tag,
			// rvaca submits — see top-of-file note on the firstOr quirk.
			submitter: 'rvaca',
			// atester's Author stage assignment is created here, fresh,
			// with the spec's canChangeMetadata=true preserved.
			participants: [
				{user: 'dbarnes', role: 'editor'},
				{user: 'atester', role: 'author', canChangeMetadata: true},
			],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('atester');
		const page = await ctx.newPage();
		// Authors land on /dashboard/mySubmissions; see top of test 1 for
		// the rationale.
		await page.goto(
			`/index.php/publicknowledge/en/dashboard/mySubmissions?workflowSubmissionId=${submission.id}`,
		);
		const workflow = new EditorialWorkflowPage(page);

		await workflow.openPublicationPanel('Title & Abstract');

		// With canChangeMetadata=true the Save button is enabled on the
		// same panel that was disabled in the previous test.
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
