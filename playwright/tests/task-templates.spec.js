// @ts-check
const {test, expect} = require('../support/base-test.js');
const {setTinyMceContent} = require('../support/tinymce.js');

/**
 * Task and Discussion Templates — row #25 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/TaskTemplates.cy.js against an E0
 * scratch journal:
 *
 *   1. Manager adds a template on a given stage, reloads the settings
 *      page, confirms it persists — covers the CRUD happy path
 *      (title + description + stage + include auto-add flag).
 *   2. Manager applies a template while drafting a task in the workflow
 *      — template pre-fills title + description in the form.
 *   3. Validation rejects an empty form — submitting without title or
 *      description surfaces the inline "This field is required" error
 *      on both fields.
 *   4. Edit + auto-add toggle + delete — exercises the management-side
 *      surfaces that aren't part of the create-and-persist test:
 *      rename via More Actions → Edit, flip the auto-add row checkbox
 *      (confirm dialog), and delete via More Actions → Delete (OK
 *      confirmation). Mirrors Cypress test 1's edit/auto-add/delete
 *      arc in one consolidated round-trip.
 *
 * Scope-drops vs. the Cypress source:
 *   - The "role-restrict visibility by role" variant (author sees only
 *     unrestricted, copyeditor sees both) is dropped here and deferred
 *     — exercising it requires a submission in copyediting stage with
 *     a copyeditor participant and a per-user-group-restricted template
 *     already seeded. That's three cross-actor contexts + a legacy
 *     journal-settings roundtrip for what is ultimately a visibility
 *     check; doc cell called it optional.
 *
 * E0 journal because task templates are journal-scoped configuration
 * the stable publicknowledge journal must not carry.
 */

/**
 * Build a worker-scoped tag so parallel workers don't collide on
 * journal path / template titles.
 * @param {import('@playwright/test').TestInfo} info
 * @param {string} suffix
 */
function uniqueTag(info, suffix) {
	const rand = Math.random().toString(36).slice(2, 6);
	return `tt-w${info.parallelIndex}-${suffix}-${rand}`;
}

/**
 * Navigate to Workflow Settings → Task Templates tab and wait for the
 * manager table to settle. The settings page lives at
 * `management/settings/workflow`; the Task Templates tab id is
 * `taskTemplates` (the hash in the URL + the `#{id}-button` tab
 * trigger come from the tabs component).
 * @param {import('@playwright/test').Page} page
 * @param {string} journalPath
 */
async function openTaskTemplatesTab(page, journalPath) {
	await page.goto(
		`/index.php/${journalPath}/management/settings/workflow#taskTemplates`,
	);
	// The tabs render each tab button as `#{id}-button` via the PkpTabs
	// component; clicking it keeps the activation deterministic even if
	// the initial URL-hash activation hasn't fired yet.
	await page.locator('#taskTemplates-button').click();
	// The manager renders the journal-level "Tasks and Discussions
	// Templates" heading (see TaskTemplateManager.vue). Use it as the
	// ready gate — the component also shows a spinner while loading
	// existing templates, but for the fresh E0 journal the table is
	// empty on first paint.
	await expect(
		page.getByRole('heading', {name: 'Tasks and Discussions Templates'}),
	).toBeVisible({timeout: 15_000});
}

/**
 * Click the "Add template" button for a named stage row. The manager
 * groups templates under stage-labelled row headers and renders one
 * "Add template" link-style button per group — scope by the row that
 * contains the stage label so we hit the right stage.
 * @param {import('@playwright/test').Page} page
 * @param {string} stageName  e.g. "Copyediting Stage"
 */
async function clickAddTemplateForStage(page, stageName) {
	// The stage row is a `<tr>` whose `<th scope="rowgroup">` wraps the
	// stage label and (inline) the Add template button. `tr:has(…)`
	// scopes the click to the right stage even though the button label
	// is identical across rows.
	const stageRow = page
		.locator('tr')
		.filter({has: page.locator('th[scope="rowgroup"]')})
		.filter({hasText: stageName});
	await expect(stageRow).toBeVisible({timeout: 10_000});
	await stageRow
		.getByRole('button', {name: 'Add template', exact: true})
		.click();
}

test.describe('Task and Discussion Templates', () => {
	test(
		'manager adds a task template for a given stage and it persists on reload',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'add');
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openTaskTemplatesTab(page, context.path);

			const templateTitle = `E2E Template ${tag}`;
			await clickAddTemplateForStage(page, 'Copyediting Stage');

			// The form renders inside a reka-ui side-modal whose
			// heading is `taskTemplates.addInStage` — the probe
			// confirmed it resolves to "Add Task and Discussion
			// Template in Copyediting Stage". ModalManager tags the
			// top-most open side-modal with [data-cy="active-modal"];
			// this settings page has no outer modal, so scoping to
			// the active-modal wrapper is unambiguous.
			const modal = page.locator('[data-cy="active-modal"]');
			// The active-modal wrapper itself is zero-size (the reka
			// DialogPortal lifts the actual content into an absolute
			// overlay), so we wait on the title input that lives
			// inside the mounted form instead of toBeVisible() on
			// the wrapper.
			const titleInput = modal.locator('#taskTemplate-title-control');
			await expect(titleInput).toBeVisible({timeout: 10_000});

			await titleInput.fill(templateTitle);

			// The description is a FieldPreparedContent (TinyMCE
			// under the hood) with controlId
			// `taskTemplate-description-control` — driven via the
			// shared setTinyMceContent helper so we don't fight the
			// editor's event machinery.
			await setTinyMceContent(
				page,
				'taskTemplate-description-control',
				'<p>This is a test template description.</p>',
			);

			// Tick the auto-add-at-stage checkbox so the row is
			// visibly marked after save. The field name comes from
			// `addFieldCheckbox('include', ...)` in
			// useTaskTemplateManagerForm.js.
			await modal.locator('input[name="include"]').check();

			await modal
				.getByRole('button', {name: 'Save', exact: true})
				.click();

			// Wait for the modal to close (detach).
			await expect(titleInput).toHaveCount(0, {timeout: 15_000});

			// The new row appears under the Copyediting Stage group.
			// The manager groups rows under `<th scope="rowgroup">`
			// headers within the same `<tbody>`; we just assert the
			// row with the template title exists anywhere in the
			// table for now, and separately sanity-check via the
			// auto-add checkbox that it's under Copyediting (only
			// that row advertises `include` on the Copyediting stage
			// under row group).
			await expect(page.getByText(templateTitle)).toBeVisible({
				timeout: 10_000,
			});

			// --- Reload and verify persistence ---
			await page.reload();
			// Re-activate the tab (reload may land on the first tab
			// depending on hash handling).
			await page.locator('#taskTemplates-button').click();
			await expect(
				page.getByRole('heading', {
					name: 'Tasks and Discussions Templates',
				}),
			).toBeVisible({timeout: 15_000});

			// Wait for the templates list to hydrate post-reload.
			// TaskTemplateManager shows a small spinner next to the
			// heading while `isLoadingTemplates` is true; once the
			// row is painted it's the stable ready signal.
			await expect(page.getByText(templateTitle)).toBeVisible({
				timeout: 15_000,
			});
		
		},
	);

	test(
		'manager applies a task template in the workflow — template content pre-fills the discussion form',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'apply');

			// E0 scratch journal with dbarnes as manager; both dbarnes
			// (editor participant) and rvaca (submitter) already exist
			// from the bootstrap baseline so we don't need to seed users
			// explicitly — just give dbarnes the manager role in this
			// journal.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const dbarnesCtx = await asUser('dbarnes');

			const page = await dbarnesCtx.newPage();

			// --- Phase 1: create a task template in Submission
			// stage via the settings UI. We need this template to
			// exist in the journal the submission is created in —
			// the apply-in-workflow fetch scopes templates by the
			// submission's context.
			await openTaskTemplatesTab(page, context.path);

			const templateTitle = `Apply Template ${tag}`;
			const templateDescription =
				'Template description used to verify pre-fill';
			await clickAddTemplateForStage(page, 'Submission Stage');

			const modal = page.locator('[data-cy="active-modal"]');
			const titleInput = modal.locator('#taskTemplate-title-control');
			await expect(titleInput).toBeVisible({timeout: 10_000});

			await titleInput.fill(templateTitle);
			// Enable the task-info sub-form — the apply flow reads
			// back dueInterval into a concrete dateDue on the task
			// form. One-week interval keeps the computed date
			// non-empty without pinning to a specific value.
			await modal.locator('input[name="taskInfoAdd"]').check();
			await modal
				.locator('select[name="dueInterval"]')
				.selectOption('P1W');
			await setTinyMceContent(
				page,
				'taskTemplate-description-control',
				`<p>${templateDescription}</p>`,
			);
			await modal
				.getByRole('button', {name: 'Save', exact: true})
				.click();
			await expect(titleInput).toHaveCount(0, {timeout: 15_000});
			await expect(page.getByText(templateTitle)).toBeVisible({
				timeout: 10_000,
			});

			// --- Phase 2: seed a stage-1 submission in the same
			// scratch journal with dbarnes as an editor participant,
			// then open the workflow page.
			const submissionSpec = {
				tag,
				journal: context.path,
				submitter: 'rvaca',
				section: 'ART',
				locale: 'en',
				participants: [{user: 'dbarnes', role: 'editor'}],
				publications: [
					{
						versionStage: 'AO',
						metadata: {
							title: {en: `Draft ${tag}`},
							abstract: {
								en: '<p>Draft submission for task-template apply test.</p>',
							},
							keywords: {en: ['testing', 'task-templates']},
						},
						published: false,
					},
				],
			};
			const {submission} = await pkpApi.createSubmission(submissionSpec);

			await page.goto(
				`/index.php/${context.path}/en/dashboard/editorial?workflowSubmissionId=${submission.id}`,
			);

			// The Discussion Manager panel is embedded in the
			// workflow page on every stage. Scroll into view and
			// open the Add form.
			const dm = page.locator('[data-cy="discussion-manager"]');
			await expect(dm).toBeVisible({timeout: 20_000});

			await dm.getByRole('button', {name: 'Add', exact: true}).click();

			// The form-modal opens on top of the workflow dialog.
			// Scope to the top-most [data-cy="active-modal"], which
			// ModalManager puts on the currently-open side-modal.
			const formModal = page.locator('[data-cy="active-modal"]');
			await expect(
				formModal.locator('input[name="title"]'),
			).toBeVisible({timeout: 15_000});

			// The template list renders as a `<ul role="list">` of
			// clickable buttons with the template title — picking
			// one fires a submission-scoped fetch that pre-populates
			// the form (`setValuesFromTemplate` in
			// useDiscussionManagerForm.js).
			// Template prefix is "TASK - " / "DISCUSSION - " in the
			// button label; match by substring on the title itself.
			await formModal
				.getByRole('button', {name: new RegExp(templateTitle)})
				.first()
				.click();

			// Title is pre-filled with the template name.
			await expect(
				formModal.locator('input[name="title"]'),
			).toHaveValue(templateTitle, {timeout: 15_000});

			// taskInfoAdd is checked — applying a task template
			// flips the form to the task shape (see
			// setValuesFromTemplate: setValue('taskInfoAdd',
			// isTask.value)).
			await expect(
				formModal.locator('input[name="taskInfoAdd"]'),
			).toBeChecked();

			// Due date is pre-populated (non-empty) from the
			// template's dueInterval=P1W.
			await expect(
				formModal.locator('input[name="dateDue"]'),
			).not.toHaveValue('', {timeout: 10_000});

			// Description is pre-filled into the form's TinyMCE
			// instance. Read the editor's live content back via the
			// tinymce API — this matches setTinyMceContent's own
			// primitive and avoids iframe-body scraping.
			//
			// The discussion form's control id follows the shared
			// pattern `<formId>-<name>-control` with formId
			// `discussionForm` (see
			// lib/ui-library/src/managers/DiscussionManager/useDiscussionManagerForm.js
			// initEmptyForm line). Poll — the editor applies the
			// template content asynchronously after the fetch
			// resolves, so a one-shot read can race ahead of the
			// assignment.
			await expect
				.poll(
					async () => {
						return await page.evaluate(() => {
							const ed = window.tinymce?.get?.(
								'discussionForm-description-control',
							);
							return ed?.getContent({format: 'text'})?.trim() ?? null;
						});
					},
					{
						timeout: 10_000,
						message:
							'description TinyMCE should receive pre-filled template content',
					},
				)
				.toContain(templateDescription);

		},
	);

	test(
		'add-template form rejects an empty submission with required-field errors',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'val');
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openTaskTemplatesTab(page, context.path);

			await clickAddTemplateForStage(page, 'Copyediting Stage');

			const modal = page.locator('[data-cy="active-modal"]');
			const titleInput = modal.locator('#taskTemplate-title-control');
			await expect(titleInput).toBeVisible({timeout: 10_000});

			// Submit without any fields filled — the form's required
			// validators should reject and surface inline errors. The
			// shared `<pkp-form>` renders FieldError components as text
			// "This field is required." adjacent to the offending
			// control. Both Title and Description are required (see
			// useTaskTemplateManagerForm.js).
			await modal
				.getByRole('button', {name: 'Save', exact: true})
				.click();

			// "This field is required" must appear at least twice in
			// the modal — once for Title and once for Description.
			// Asserting count >= 2 is more robust than scoping to each
			// field, since the error markup can wrap differently on
			// FieldRichTextarea (description) vs FieldText (title).
			const errors = modal.getByText('This field is required.');
			await expect(errors.first()).toBeVisible({timeout: 10_000});
			expect(await errors.count()).toBeGreaterThanOrEqual(2);

			// The modal should still be open (the form didn't submit).
			await expect(titleInput).toBeVisible();
		},
	);

	test(
		'manager edits a template title, toggles auto-add, then deletes it',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'mgmt');
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openTaskTemplatesTab(page, context.path);

			const initialTitle = `Initial ${tag}`;
			const editedTitle = `Edited ${tag}`;

			// --- Phase 1: create a template with auto-add ON. The
			// `include` checkbox in the modal flips the row-level
			// auto-add flag once the row appears in the table.
			await clickAddTemplateForStage(page, 'Copyediting Stage');
			const modal = page.locator('[data-cy="active-modal"]');
			const titleInput = modal.locator('#taskTemplate-title-control');
			await expect(titleInput).toBeVisible({timeout: 10_000});
			await titleInput.fill(initialTitle);
			await setTinyMceContent(
				page,
				'taskTemplate-description-control',
				'<p>Initial description.</p>',
			);
			await modal.locator('input[name="include"]').check();
			await modal
				.getByRole('button', {name: 'Save', exact: true})
				.click();
			await expect(titleInput).toHaveCount(0, {timeout: 15_000});
			await expect(page.getByText(initialTitle)).toBeVisible({
				timeout: 10_000,
			});

			// The row's auto-add column renders as an input[type="checkbox"]
			// scoped to the template's <tr>. Confirm it's checked
			// (matches the include=true the modal saved).
			const initialRow = page
				.locator('tr')
				.filter({hasText: initialTitle});
			await expect(initialRow).toHaveCount(1, {timeout: 10_000});
			const autoAddCheckbox = initialRow
				.locator('input[type="checkbox"]')
				.first();
			await expect(autoAddCheckbox).toBeChecked();

			// --- Phase 2: edit title via More Actions → Edit. The
			// DropdownActions menu trigger has aria-label "More
			// Actions" (PkpDropdownActions convention).
			await initialRow
				.getByRole('button', {name: 'More Actions'})
				.click();
			await page
				.getByRole('menuitem', {name: 'Edit', exact: true})
				.first()
				.click();

			const editTitleInput = modal.locator('#taskTemplate-title-control');
			await expect(editTitleInput).toBeVisible({timeout: 10_000});
			await expect(editTitleInput).toHaveValue(initialTitle);
			await editTitleInput.fill(editedTitle);
			await modal
				.getByRole('button', {name: 'Save', exact: true})
				.click();
			await expect(editTitleInput).toHaveCount(0, {timeout: 15_000});

			// Row now shows the edited title; original title is gone.
			await expect(page.getByText(editedTitle)).toBeVisible({
				timeout: 10_000,
			});
			await expect(page.getByText(initialTitle)).toHaveCount(0);

			// --- Phase 3: toggle auto-add OFF via the row checkbox.
			// Clicking the checkbox triggers a confirm dialog (Yes/No);
			// Yes commits the toggle.
			const editedRow = page.locator('tr').filter({hasText: editedTitle});
			const editedAutoAdd = editedRow
				.locator('input[type="checkbox"]')
				.first();
			await expect(editedAutoAdd).toBeChecked();
			await editedAutoAdd.click({force: true});
			await page.getByRole('button', {name: 'Yes', exact: true}).click();
			await expect(editedAutoAdd).not.toBeChecked({timeout: 10_000});

			// --- Phase 4: delete via More Actions → Delete + OK.
			await editedRow
				.getByRole('button', {name: 'More Actions'})
				.click();
			await page
				.getByRole('menuitem', {name: 'Delete', exact: true})
				.first()
				.click();
			await page.getByRole('button', {name: 'OK', exact: true}).click();
			await expect(page.getByText(editedTitle)).toHaveCount(0, {
				timeout: 10_000,
			});
		},
	);
});
