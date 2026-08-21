// @ts-check
const {test, expect} = require('../support/base-test.js');
const {setTinyMceContent} = require('../support/tinymce.js');

/**
 * Announcements CRUD — row #1 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/Announcements.cy.js.
 *
 * Uses E0 scratch journals so creating / editing / deleting rows
 * can't leak back to the bootstrapped publicknowledge journal.
 *
 * Four tests:
 *   1. CRUD round-trip on the announcements admin (create + edit +
 *      delete via the listPanel actions).
 *   2. Enable/Disable toggle on Website Settings → Setup → Announcements
 *      sub-tab, with the nav-item presence asserting the toggle's
 *      effect end-to-end.
 *   3. Reader-side `/announcement` listing page renders a published
 *      announcement on an enableAnnouncements-true journal.
 *   4. Sitemap XML at `/{journal}/sitemap` includes an announcement
 *      URL once enableAnnouncements is true.
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

	test(
		'manager toggles enableAnnouncements via Website Settings; nav item appears + disappears',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Toggle scratch ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();

			// Open Website Settings → Setup outer tab → Announcements
			// inner sub-tab. PkpTabs id-anchored: outer
			// `#setup-button`, inner sub-tab carries
			// `#announcements-button` once the Setup tab has activated.
			await page.goto(
				`/index.php/${context.path}/management/settings/website#announcements`,
			);
			await page.locator('#setup-button').click();
			await page.locator('#announcements-button').click();

			// "Enable announcements" is a FieldOptions checkbox inside
			// the announcements form. Tick it + Save (race with context
			// PUT).
			const enableLabel = page.locator('label', {
				hasText: 'Enable announcements',
			});
			await expect(enableLabel.first()).toBeVisible({timeout: 15_000});
			await enableLabel.first().click();
			const annForm = page.locator('#announcements form').first();
			await Promise.all([
				page.waitForResponse(
					(res) =>
						/\/api\/v1\/contexts\/\d+/.test(res.url()) &&
						res.ok() &&
						['POST', 'PUT'].includes(res.request().method()),
					{timeout: 15_000},
				),
				annForm.getByRole('button', {name: 'Save', exact: true}).click(),
			]);

			// Reload + reactivate the sub-tab; assert the nav-item
			// appears (the dashboard side-nav adds an "Announcements"
			// entry when enableAnnouncements is true).
			await page.reload();
			await expect(
				page.locator('nav').getByText('Announcements').first(),
			).toBeVisible({timeout: 15_000});

			// Disable: navigate back, untick, save, reload, assert
			// nav-item is gone.
			await page.goto(
				`/index.php/${context.path}/management/settings/website#announcements`,
			);
			await page.locator('#setup-button').click();
			await page.locator('#announcements-button').click();
			await page
				.locator('label', {hasText: 'Enable announcements'})
				.first()
				.click();
			await Promise.all([
				page.waitForResponse(
					(res) =>
						/\/api\/v1\/contexts\/\d+/.test(res.url()) &&
						res.ok() &&
						['POST', 'PUT'].includes(res.request().method()),
					{timeout: 15_000},
				),
				page
					.locator('#announcements form')
					.first()
					.getByRole('button', {name: 'Save', exact: true})
					.click(),
			]);

			await page.reload();
			// Wait for the reload's nav-render to settle, then assert
			// no Announcements link exists in the side-nav.
			await expect(
				page.locator('nav').getByText('Announcements'),
			).toHaveCount(0, {timeout: 15_000});
		},
	);

	test(
		'reader sees a published announcement on the public /announcement page',
		{tag: '@regression'},
		async ({pkpApi, asUser, browser, baseURL}) => {
			const tag = uniqueTag();
			// Seed enableAnnouncements via ContextBuilderProcessor so
			// the reader-side route is wired without a separate UI
			// flow (covered by test 2).
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Reader-announce ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
				enableAnnouncements: true,
			});
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();

			// Create an announcement so the reader page has content.
			await page.goto(
				`/index.php/${context.path}/management/settings/announcements`,
			);
			await expect(
				page.getByRole('button', {name: 'Add Announcement'}),
			).toBeVisible({timeout: 15_000});
			await page
				.getByRole('button', {name: 'Add Announcement'})
				.click();
			const dialog = page.getByRole('dialog');
			const annTitle = `Reader CFP ${tag}`;
			await dialog
				.locator('#announcement-title-control-en')
				.fill(annTitle);
			await setTinyMceContent(
				page,
				'announcement-descriptionShort-control-en',
				`<p>Reader-side teaser ${tag}.</p>`,
			);
			await dialog.getByRole('button', {name: 'Save', exact: true}).click();
			await expect(
				page.locator('#announcements .listPanel__itemSummary', {
					hasText: annTitle,
				}),
			).toBeVisible({timeout: 15_000});

			// Anonymous reader navigates to /announcement.
			const anon = await browser.newContext({baseURL});
			try {
				const reader = await anon.newPage();
				const resp = await reader.goto(
					`/index.php/${context.path}/announcement`,
				);
				expect(resp?.status()).toBe(200);
				await expect(reader.getByText(annTitle).first()).toBeVisible({
					timeout: 15_000,
				});
			} finally {
				await anon.close();
			}
		},
	);

	test(
		'sitemap XML contains an announcement URL once announcements are enabled',
		{tag: '@regression'},
		async ({pkpApi, asUser, request}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Sitemap-announce ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
				enableAnnouncements: true,
			});

			// Seed an announcement so the sitemap has content to
			// surface (the journal-level Announcements URL appears
			// regardless, but Cypress's assertion was on the keyword
			// "announcement" anywhere in a <loc>, which the journal
			// route satisfies).
			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await page.goto(
				`/index.php/${context.path}/management/settings/announcements`,
			);
			await page
				.getByRole('button', {name: 'Add Announcement'})
				.click();
			const dialog = page.getByRole('dialog');
			await dialog
				.locator('#announcement-title-control-en')
				.fill(`Sitemap announcement ${tag}`);
			await setTinyMceContent(
				page,
				'announcement-descriptionShort-control-en',
				`<p>Body ${tag}.</p>`,
			);
			await dialog.getByRole('button', {name: 'Save', exact: true}).click();

			// Anonymous request to /{journal}/sitemap (XML). The
			// site-wide route at /sitemap aggregates all enabled
			// journals; the per-journal one keeps assertions scoped
			// to this scratch context.
			const sitemapResp = await request.get(
				`/index.php/${context.path}/sitemap`,
			);
			expect(sitemapResp.status()).toBe(200);
			expect(sitemapResp.headers()['content-type']).toMatch(
				/application\/xml/i,
			);
			const xml = await sitemapResp.text();
			// Cypress asserted on a `<loc>` containing "announcement";
			// a substring match is enough (the regex catches both
			// /announcement listing and /announcement/view/{id} entries).
			expect(xml).toMatch(/<loc>[^<]*announcement[^<]*<\/loc>/i);
		},
	);
});
