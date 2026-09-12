// @ts-check
const {test, expect} = require('../support/base-test.js');
const {setTinyMceContent} = require('../support/tinymce.js');
/**
 * Email templates — row #4 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/emailTemplates/EmailTemplates.cy.js
 * (9 tests) into 4 focused tests:
 *
 *   1. edit-default: toggle an existing mailable's default template
 *      from unrestricted -> restricted and assign user groups. Folds
 *      Cypress's bidirectional toggles (Marks/Removes unrestricted) and
 *      the assign/remove user-groups pair into the single round-trip;
 *      what changes between Cypress's tests 1-4 is direction, not
 *      surface, so a single save→reload→re-open round-trip covers them.
 *   2. custom-restricted: create a new custom template with body +
 *      two user groups assigned. Cypress test 5.
 *   3. custom-unrestricted: create a new custom template with
 *      unrestricted=true, confirm the user-group checkboxes are NOT
 *      rendered, then flip the radio back to restricted within the
 *      same session and confirm the user-group checkboxes reappear.
 *      Folds Cypress tests 6, 8 (hide-direction) and 9 (show-direction
 *      reactive reveal).
 *   4. custom-restricted-no-groups: create a restricted custom template
 *      with zero user groups assigned and confirm the form accepts the
 *      zero-UG state. Cypress test 7 — the "is the validator OK with no
 *      UGs?" surface, distinct from tests 5/6's "save with checks".
 *
 * Each test seeds its own E0 scratch journal so the bootstrapped
 * publicknowledge journal's email templates stay untouched.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `em-w${workerIndex}-${suffix}`;
}

async function openManageEmails(page, journalPath) {
	await page.goto(
		`/index.php/${journalPath}/management/settings/manageEmails`,
	);
	// The mailables list is the first listPanel on the page; wait for
	// at least one item to render before any test proceeds.
	await expect(page.locator('li.listPanel__item').first()).toBeVisible();
}

/**
 * Click the Edit button on a mailable row in the outer Manage Emails
 * list. The button renders a visible "Edit" glyph with aria-hidden plus
 * a screen-reader-only "Edit {$name}" label, so the accessible name is
 * "Edit Discussion (Production)" (or whichever mailable).
 */
async function clickEditOnMailable(page, mailableName) {
	await page
		.locator('li.listPanel__item', {hasText: mailableName})
		.getByRole('button', {name: `Edit ${mailableName}`})
		.first()
		.click();
}

/**
 * Return a locator scoped to the "mailable" modal — i.e., the
 * EditMailableModal whose inner Templates listPanel is rendered.
 * Reka-ui's DialogPortal keeps two nested side-modals mounted
 * simultaneously when the template form is open on top of a mailable,
 * so scoping by a distinctive inner element (the Add Template button
 * only ever exists in the mailable modal) is how we disambiguate.
 */
function mailableModalLocator(page) {
	return page
		.locator('[data-cy="active-modal"]')
		.filter({has: page.getByRole('button', {name: 'Add Template'})});
}

/**
 * Return a locator scoped to the "template" modal — the
 * EditTemplateModal stacked on top of the mailable modal. Filter on
 * the isUnrestricted radio, which only exists in the template form.
 */
function templateModalLocator(page) {
	return page
		.locator('[data-cy="active-modal"]')
		.filter({has: page.locator('input[name="isUnrestricted"]')});
}

/**
 * Open a mailable by name and click Edit on one of its templates.
 *
 * The mailable row in the outer listPanel has an "Edit" action that
 * opens the EditMailableModal side-modal. The side-modal itself
 * contains a second (nested) listPanel of templates; each template
 * row also has an "Edit" button that opens the EditTemplateModal on
 * top of the mailable modal. This helper performs both clicks.
 */
async function openEmailTemplate(page, mailableName, templateName) {
	await clickEditOnMailable(page, mailableName);

	const mailableModal = mailableModalLocator(page);
	await expect(mailableModal).toHaveCount(1);

	// The template row's Edit button has plain text "Edit".
	await mailableModal
		.locator('li.listPanel__item', {hasText: templateName})
		.getByRole('button', {name: 'Edit'})
		.click();

	const templateModal = templateModalLocator(page);
	await expect(templateModal).toHaveCount(1);
	return templateModal;
}

async function openNewTemplateForMailable(page, mailableName) {
	await clickEditOnMailable(page, mailableName);

	const mailableModal = mailableModalLocator(page);
	await expect(mailableModal).toHaveCount(1);
	await mailableModal.getByRole('button', {name: 'Add Template'}).click();

	const templateModal = templateModalLocator(page);
	await expect(templateModal).toHaveCount(1);
	return templateModal;
}

async function saveTemplateModal(page) {
	const templateModal = templateModalLocator(page);
	await templateModal.getByRole('button', {name: 'Save'}).click();
	// After save the side-modal auto-closes after a delay (see
	// ManageEmailsPage.vue's templateSaved). Wait for the template
	// modal to go away before continuing so that a subsequent reload
	// doesn't race an in-flight PUT.
	await expect(templateModal).toHaveCount(0, {timeout: 15_000});
}

async function setUnrestricted(modal, value) {
	// The FieldOptions radio renders value="true" / value="false" as
	// the literal strings; casting to bool in PHP then JSON-encoding
	// produces those.
	const target = value ? 'true' : 'false';
	await modal
		.locator(`input[name="isUnrestricted"][value="${target}"]`)
		.check({force: true});
}

test.describe('Email templates', () => {
	test(
		'admin toggles a default template from unrestricted to restricted with user groups',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openManageEmails(page, context.path);

			const mailable = 'Discussion (Production)';

			// Open the default template, flip to restricted, pick two
			// user groups, save.
			let templateModal = await openEmailTemplate(page, mailable, mailable);
			await setUnrestricted(templateModal, false);
			await templateModal
				.locator('input[name="assignedUserGroupIds"]')
				.nth(0)
				.check({force: true});
			await templateModal
				.locator('input[name="assignedUserGroupIds"]')
				.nth(1)
				.check({force: true});
			await saveTemplateModal(page);

			// Reload and verify the selection persisted.
			await page.reload();
			await expect(page.locator('li.listPanel__item').first()).toBeVisible();
			templateModal = await openEmailTemplate(page, mailable, mailable);
			await expect(
				templateModal.locator('input[name="isUnrestricted"]:checked'),
			).toHaveValue('false');
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]').nth(0),
			).toBeChecked();
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]').nth(1),
			).toBeChecked();
		
		},
	);

	test(
		'admin adds a new restricted custom template with body and two user groups',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openManageEmails(page, context.path);

			const mailable = 'Discussion (Production)';
			const templateName = `Custom restricted ${tag}`;

			let templateModal = await openNewTemplateForMailable(page, mailable);

			await templateModal
				.locator('input[id^="editEmailTemplate-name-control-en"]')
				.fill(templateName);
			await templateModal
				.locator('input[id^="editEmailTemplate-subject-control-en"]')
				.fill(`Subject for ${tag}`);
			await setTinyMceContent(
				page,
				'editEmailTemplate-body-control-en',
				`<p>Body for ${tag}</p>`,
			);

			await setUnrestricted(templateModal, false);
			// Cypress seeds the two "middle" user groups (indices 1 and 2)
			// because the test on publicknowledge left index 0 assigned
			// elsewhere; on a scratch journal nothing else is wired, so
			// indices 0 and 1 are fine.
			await templateModal
				.locator('input[name="assignedUserGroupIds"]')
				.nth(0)
				.check({force: true});
			await templateModal
				.locator('input[name="assignedUserGroupIds"]')
				.nth(1)
				.check({force: true});

			await saveTemplateModal(page);

			// Reload and verify the custom template persisted with both
			// user groups still checked.
			await page.reload();
			await expect(page.locator('li.listPanel__item').first()).toBeVisible();
			templateModal = await openEmailTemplate(page, mailable, templateName);
			await expect(
				templateModal.locator('input[name="isUnrestricted"]:checked'),
			).toHaveValue('false');
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]').nth(0),
			).toBeChecked();
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]').nth(1),
			).toBeChecked();
		
		},
	);

	test(
		'admin creates an unrestricted custom template and user-group options are hidden',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openManageEmails(page, context.path);

			const mailable = 'Discussion (Production)';
			const templateName = `Custom unrestricted ${tag}`;

			let templateModal = await openNewTemplateForMailable(page, mailable);

			await templateModal
				.locator('input[id^="editEmailTemplate-name-control-en"]')
				.fill(templateName);
			await templateModal
				.locator('input[id^="editEmailTemplate-subject-control-en"]')
				.fill(`Subject for ${tag}`);
			await setTinyMceContent(
				page,
				'editEmailTemplate-body-control-en',
				`<p>Body for ${tag}</p>`,
			);

			await setUnrestricted(templateModal, true);
			// User-group checkboxes are gated on isUnrestricted=false via
			// FieldOptions's showWhen — with unrestricted=true they must
			// not be in the DOM at all.
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]'),
			).toHaveCount(0);

			// Reactive show direction: flipping the radio back to
			// restricted in the same session must re-mount the
			// user-group checkboxes (FieldOptions remounts the dependent
			// field on showWhen-truthy). Then flip back to unrestricted
			// before saving so the persisted state still matches the
			// test name.
			await setUnrestricted(templateModal, false);
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]').first(),
			).toBeVisible();
			await setUnrestricted(templateModal, true);
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]'),
			).toHaveCount(0);

			await saveTemplateModal(page);

			await page.reload();
			await expect(page.locator('li.listPanel__item').first()).toBeVisible();
			templateModal = await openEmailTemplate(page, mailable, templateName);
			await expect(
				templateModal.locator('input[name="isUnrestricted"]:checked'),
			).toHaveValue('true');
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]'),
			).toHaveCount(0);

		},
	);

	test(
		'admin creates a restricted custom template with no assigned user groups',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openManageEmails(page, context.path);

			// Cypress test 7 anchors on a different mailable
			// (`Reinstate Submission Declined Without Review`) than tests
			// 5/6's `Discussion (Production)` — the validator's behaviour
			// is per-mailable in principle but the no-UG-restricted state
			// is permitted everywhere, so we stay on the same mailable
			// the other Playwright tests use to keep one less moving
			// part. The point of this test is "form accepts a restricted
			// template with zero UGs", not "this specific mailable".
			const mailable = 'Discussion (Production)';
			const templateName = `Custom restricted (no groups) ${tag}`;

			let templateModal = await openNewTemplateForMailable(page, mailable);

			await templateModal
				.locator('input[id^="editEmailTemplate-name-control-en"]')
				.fill(templateName);
			await templateModal
				.locator('input[id^="editEmailTemplate-subject-control-en"]')
				.fill(`Subject for ${tag}`);
			await setTinyMceContent(
				page,
				'editEmailTemplate-body-control-en',
				`<p>Body for ${tag}</p>`,
			);

			await setUnrestricted(templateModal, false);
			// Confirm the user-group checkboxes ARE rendered (showWhen
			// flipped on by isUnrestricted=false) but explicitly leave
			// every box unchecked — the surface is "form saves with zero
			// UGs assigned".
			await expect(
				templateModal.locator('input[name="assignedUserGroupIds"]').first(),
			).toBeVisible();
			const ugCheckboxes = templateModal.locator(
				'input[name="assignedUserGroupIds"]',
			);
			const ugCount = await ugCheckboxes.count();
			for (let i = 0; i < ugCount; i++) {
				await expect(ugCheckboxes.nth(i)).not.toBeChecked();
			}

			await saveTemplateModal(page);

			// Reload and confirm the persisted state: restricted, but no
			// user groups checked.
			await page.reload();
			await expect(page.locator('li.listPanel__item').first()).toBeVisible();
			templateModal = await openEmailTemplate(page, mailable, templateName);
			await expect(
				templateModal.locator('input[name="isUnrestricted"]:checked'),
			).toHaveValue('false');
			const reloadedUgCount = await templateModal
				.locator('input[name="assignedUserGroupIds"]')
				.count();
			for (let i = 0; i < reloadedUgCount; i++) {
				await expect(
					templateModal.locator('input[name="assignedUserGroupIds"]').nth(i),
				).not.toBeChecked();
			}
		},
	);
});
