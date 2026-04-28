// @ts-check
const {test, expect} = require('../support/base-test.js');
const {setTinyMceContent} = require('../support/tinymce.js');
const {ensureAuthStateFor} = require('../support/auth.js');

/**
 * Email templates — row #4 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/emailTemplates/EmailTemplates.cy.js.
 *
 * The Cypress file has 9 near-duplicate tests (each of four boolean
 * combinations of unrestricted + user-group has its own "Assign…",
 * "Remove…", "Mark…", "Hide…" test). Collapsed to 3 focused tests
 * covering the same surface:
 *
 *   1. edit-default: toggle an existing mailable's default template
 *      from unrestricted -> restricted and assign user groups.
 *   2. custom-restricted: create a new custom template with body +
 *      two user groups assigned (restricted).
 *   3. custom-unrestricted: create a new custom template with
 *      unrestricted=true and confirm the user-group checkboxes are
 *      not rendered.
 *
 * Each test seeds its own E0 scratch journal so the bootstrapped
 * publicknowledge journal's email templates stay untouched.
 *
 * The Cypress "reset" + "delete custom template" flows are not ported
 * here — they're pure UI confirmations of the API-level operations and
 * don't meaningfully extend coverage beyond (2)+(3). Worth adding later
 * if we start regressing the reset/delete confirmation dialogs.
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
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
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
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'admin adds a new restricted custom template with body and two user groups',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
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
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'admin creates an unrestricted custom template and user-group options are hidden',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
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
			} finally {
				await ctx.close();
			}
		},
	);
});
