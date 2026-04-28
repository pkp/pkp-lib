// @ts-check
const {test, expect} = require('../support/base-test.js');
const {setTinyMceContent} = require('../support/tinymce.js');

/**
 * Announcements CRUD — row #1 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/Announcements.cy.js.
 *
 * Uses an E0 scratch journal so creating / editing / deleting rows
 * can't leak back to the bootstrapped publicknowledge journal.
 *
 * The Cypress spec also exercises the Website-settings "Enable
 * announcements" toggle; that's a Website-settings concern, not an
 * announcement-CRUD concern, and covering it would require the full
 * Website settings page to render (which it doesn't on a bare
 * scratch journal without additional setup). Left for a later row
 * if it's worth a dedicated spec.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `anc-w${workerIndex}-${suffix}`;
}

test.describe('Announcements', () => {
	test(
		'manager creates an announcement with TinyMCE body, edits it, then deletes it',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Announcements scratch ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();

			await page.goto(
				`/index.php/${context.path}/management/settings/announcements`,
			);
			await expect(
				page.getByRole('button', {name: 'Add Announcement'}),
			).toBeVisible();

			// --- Create ---
			await page
				.getByRole('button', {name: 'Add Announcement'})
				.click();

			const createDialog = page.getByRole('dialog');
			const titleCreate = `Call for papers ${tag}`;
			await createDialog
				.locator('#announcement-title-control-en')
				.fill(titleCreate);
			await setTinyMceContent(
				page,
				'announcement-descriptionShort-control-en',
				'<p>Short teaser for the CFP.</p>',
			);
			await createDialog.getByRole('button', {name: 'Save'}).click();

			const createdRow = page.locator(
				'#announcements .listPanel__itemSummary',
				{hasText: titleCreate},
			);
			await expect(createdRow).toBeVisible();

			// --- Edit ---
			await createdRow.getByRole('button', {name: 'Edit'}).click();
			const editDialog = page.getByRole('dialog');
			const titleEdited = `${titleCreate} (edited)`;
			await editDialog
				.locator('#announcement-title-control-en')
				.fill(titleEdited);
			await editDialog.getByRole('button', {name: 'Save'}).click();

			const editedRow = page.locator(
				'#announcements .listPanel__itemSummary',
				{hasText: titleEdited},
			);
			await expect(editedRow).toBeVisible();

			// --- Delete ---
			await editedRow.getByRole('button', {name: 'Delete'}).click();
			await page
				.getByRole('dialog')
				.getByRole('button', {name: 'Yes'})
				.click();
			await expect(
				page.locator('#announcements .listPanel__itemTitle', {
					hasText: titleEdited,
				}),
			).toHaveCount(0);
		},
	);
});
