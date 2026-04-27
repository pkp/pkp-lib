// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');

/**
 * Navigation menus — row #2 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/NavigationMenus.cy.js.
 *
 * The Navigation Menu Editor surface mixes two modal pipelines on the
 * same Setup → Navigation tab:
 *
 *   - The OUTER navigation-menus grid (`#navigationMenuGridContainer`)
 *     is the legacy `pkp_controllers_linkAction` jQuery grid whose
 *     "Add Menu" / row Edit / row Remove link actions now open the
 *     modern Vue `NavigationMenuManagerFormModal` (a `SideModalBody`
 *     side-modal — `[data-cy="active-modal"]`). The form inside is a
 *     PkpForm with `name="title"` + `name="areaName"` plus an embedded
 *     two-panel drag-and-drop NavigationMenuEditor
 *     (`[data-cy="navigation-menu-editor"]` with `[data-cy="assigned-panel"]`
 *     / `[data-cy="unassigned-panel"]`).
 *
 *   - The INNER navigation-menu-items grid
 *     (`#navigationMenuItemsGridContainer`) is rendered alongside the
 *     menus grid on the same tab and remains pure legacy: jQuery-UI
 *     AjaxModal opens `form#navigationMenuItemsForm` with fbv
 *     `name="title[en]"` / `name="path"` / `select[name="menuItemType"]`
 *     and per-row Edit / Remove link actions hidden behind the
 *     `a.show_extras` toggle (same row #8 sections / row #7 issues
 *     pattern).
 *
 * Each test seeds an E0 scratch journal so the bootstrapped
 * publicknowledge journal's navigation menus stay untouched.
 * `PKPContextService::add()` runs `NavigationMenuDAO::installSettings(
 * 'registry/navigationMenus.xml')` during context creation, so a
 * scratch journal already has a default Primary Navigation Menu
 * assigned to the `primary` area — the area-conflict assertion keys
 * off that default.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `nav-w${workerIndex}-${suffix}`;
}

/**
 * Visit the Navigation tab of the Website settings page. The tab is a
 * Vue `<tab id="navigationMenus">` inside the website tabset; clicking
 * the tab fires the `navigationMenuGridContainer` `load_url_in_div`
 * fetch.
 */
async function openNavigationTab(page, journalPath) {
	await page.goto(`/index.php/${journalPath}/management/settings/website`);
	await page.locator('#setup-button').click();
	await page.getByRole('tab', {name: 'Navigation'}).click();
	// Wait for both grids to mount — Add Menu link action on the menus
	// grid, Add Item link action on the items grid.
	await expect(
		page.locator('a.pkp_linkaction_addNavigationMenu'),
	).toBeVisible({timeout: 15_000});
	// The items grid renders many `a.pkp_controllers_linkAction` anchors
	// (one Add Item plus per-row Edit / Remove for each default item),
	// so `.toBeVisible()` would trip strict-mode. Existence on the
	// add-item anchor is enough to confirm the grid has hydrated.
	await expect(
		page.locator(
			'#navigationMenuItemsGridContainer a.pkp_linkaction_addNavigationMenuItem',
		),
	).toBeVisible({timeout: 15_000});
}

/**
 * Locator scoped to the active NavigationMenuManagerFormModal side-modal.
 * Filter by the `navigation-menu-editor` panel which only exists in
 * this specific modal — robust against any other side-modal stacking.
 */
function menuFormModal(page) {
	return page
		.locator('[data-cy="active-modal"]')
		.filter({has: page.locator('[data-cy="navigation-menu-editor"]')});
}

/**
 * Open the Add Menu side-modal and wait for the Vue editor to mount.
 *
 * The "Add Menu" link action is rendered as
 * `<a class="pkp_controllers_linkAction pkp_linkaction_addNavigationMenu">`
 * by the legacy grid handler — `getByRole('button', {name: 'Add Menu'})`
 * does NOT match (it's an anchor, not a button).
 */
async function openAddMenuModal(page) {
	await page.locator('a.pkp_linkaction_addNavigationMenu').click();
	const modal = menuFormModal(page);
	await expect(modal).toHaveCount(1, {timeout: 20_000});
	await expect(modal.locator('[data-cy="assigned-panel"]')).toBeVisible();
	await expect(modal.locator('[data-cy="unassigned-panel"]')).toBeVisible();
	return modal;
}

/**
 * Open the row's Edit side-modal by clicking the row's title link
 * (the legacy grid renders the title column as a clickable LinkAction
 * that opens the same NavigationMenuManagerFormModal as the Edit row
 * action).
 */
async function openEditMenuModalByTitle(page, title) {
	await page
		.locator('#navigationMenuGridContainer')
		.getByText(title, {exact: true})
		.first()
		.click();
	const modal = menuFormModal(page);
	await expect(modal).toHaveCount(1, {timeout: 20_000});
	await expect(modal.locator('[data-cy="assigned-panel"]')).toBeVisible();
	return modal;
}

/**
 * Cancel a side-modal that has unsaved changes. The PkpForm's Cancel
 * button calls `closeModal`, which goes through `useFormChanged`'s
 * `confirmClose` and opens the [data-cy="dialog"] Yes/No prompt.
 */
async function cancelWithUnsavedChanges(page, modal) {
	await modal.getByRole('button', {name: 'Cancel'}).click();
	const dialog = page.locator('[data-cy="dialog"]');
	await expect(dialog).toBeVisible({timeout: 10_000});
	await dialog.getByRole('button', {name: 'Yes'}).click();
	await expect(modal).toHaveCount(0, {timeout: 10_000});
}

/**
 * Expand a row's hidden controls by clicking its `a.show_extras` glyph,
 * then click the action whose label matches `actionText` inside the
 * sibling `tr.row_controls`. Mirrors the row #8 sections /
 * subscription-config helpers.
 */
async function clickRowAction(page, gridSelector, rowText, actionText) {
	const row = page
		.locator(`${gridSelector} tr.gridRow`, {hasText: rowText})
		.first();
	// Each row has its own settings glyph; if it's already expanded
	// (class flipped to `hide_extras` after a previous click in the
	// same render), skip the toggle.
	const showExtras = row.locator('a.show_extras');
	if ((await showExtras.count()) > 0) {
		await showExtras.click();
	}
	// row_controls is the sibling tr — the grid renders it adjacent to
	// the gridRow with a matching `${rowId}-control-row` id. Walk up to
	// the tbody and pick the visible row_controls under the same
	// container.
	await page
		.locator(`${gridSelector} tr.row_controls:visible`)
		.getByText(actionText, {exact: true})
		.first()
		.click();
}

test.describe('Navigation menus', () => {
	test(
		'manager creates a menu, edits its title, validates duplicate + area-conflict, and deletes it',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Nav Menu Scratch ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				await openNavigationTab(page, context.path);

				const menuName = `Test Nav Menu ${tag}`;
				const updatedMenuName = `${menuName} Updated`;

				// --- 1. Create ---
				let modal = await openAddMenuModal(page);

				// New-menu invariants: assigned panel is empty, unassigned
				// panel has a healthy starter set including Register / Login.
				await expect(
					modal.locator('[data-cy="assigned-panel"] [data-menu-item-title]'),
				).toHaveCount(0);
				const unassignedItems = modal.locator(
					'[data-cy="unassigned-panel"] [data-menu-item-title]',
				);
				expect(await unassignedItems.count()).toBeGreaterThan(4);
				await expect(
					modal.locator('[data-cy="unassigned-panel"]').getByText('Register'),
				).toBeVisible();
				await expect(
					modal.locator('[data-cy="unassigned-panel"]').getByText('Login'),
				).toBeVisible();

				await modal.locator('input[name="title"]').fill(menuName);
				await modal.getByRole('button', {name: 'Save'}).click();
				await expect(modal).toHaveCount(0, {timeout: 15_000});

				// New row appears in the menus grid.
				await expect(
					page
						.locator('#navigationMenuGridContainer tr.gridRow', {
							hasText: menuName,
						})
						.first(),
				).toBeVisible({timeout: 15_000});

				// --- 2. Edit title (clicking the row title opens the same
				// NavigationMenuManagerFormModal in edit mode) ---
				modal = await openEditMenuModalByTitle(page, menuName);
				const titleInput = modal.locator('input[name="title"]');
				await expect(titleInput).toHaveValue(menuName);
				await titleInput.fill(updatedMenuName);
				await modal.getByRole('button', {name: 'Save'}).click();
				await expect(modal).toHaveCount(0, {timeout: 15_000});

				await expect(
					page
						.locator('#navigationMenuGridContainer tr.gridRow', {
							hasText: updatedMenuName,
						})
						.first(),
				).toBeVisible({timeout: 15_000});

				// --- 3. Duplicate-title validation ---
				modal = await openAddMenuModal(page);
				await modal.locator('input[name="title"]').fill(updatedMenuName);
				await modal.getByRole('button', {name: 'Save'}).click();
				// Modal stays open; the inline error message is "This title
				// already exists for another navigation menu." (per
				// PKPNavigationMenuController + manager.po). The Cypress
				// source greps the prefix "This title already exists" — keep
				// the same anchor here so a future copy edit on the period
				// or sentence tail doesn't break the test. PkpForm renders
				// the message twice — once inline next to the field, once
				// in the form-level error footer ("Go to Title: …"); use
				// `.first()` so strict mode is happy.
				await expect(
					modal
						.getByText('This title already exists', {exact: false})
						.first(),
				).toBeVisible({timeout: 10_000});
				await cancelWithUnsavedChanges(page, modal);

				// --- 4. Area-conflict validation ---
				// Default Primary Navigation Menu (installed by
				// NavigationMenuDAO::installSettings) already occupies the
				// `primary` area. Selecting it on a new menu trips
				// PKPNavigationMenuController#304-310's getByArea check.
				modal = await openAddMenuModal(page);
				await modal.locator('input[name="title"]').fill(`${menuName} 2`);
				await modal.locator('select[name="areaName"]').selectOption('primary');
				await modal.getByRole('button', {name: 'Save'}).click();
				await expect(
					modal
						.getByText(
							'A navigation menu is already assigned to this area',
							{exact: false},
						)
						.first(),
				).toBeVisible({timeout: 10_000});
				await cancelWithUnsavedChanges(page, modal);

				// --- 5. Delete ---
				await clickRowAction(
					page,
					'#navigationMenuGridContainer',
					updatedMenuName,
					'Remove',
				);
				// RemoteActionConfirmationModal opens a jQuery-UI dialog
				// with an OK button.
				await page.getByRole('button', {name: 'OK'}).click();
				await expect(
					page.locator('#navigationMenuGridContainer', {
						hasText: updatedMenuName,
					}),
				).toHaveCount(0, {timeout: 15_000});
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'manager creates, edits, and deletes a custom navigation menu item',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				name: {en: `Nav Item Scratch ${tag}`},
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				await openNavigationTab(page, context.path);

				const itemTitle = `Test Custom Item ${tag}`;
				const itemPath = `test-custom-item-${tag}`;
				const updatedTitle = `${itemTitle} Updated`;

				// The items grid sits on the same tab. Its "Add Item" link
				// action opens a jQuery-UI AjaxModal hosting
				// `form#navigationMenuItemsForm` (legacy fbv). Scope by the
				// stable add-item class so we don't accidentally hit a
				// per-row Edit / Remove anchor (the grid renders dozens
				// from the default Primary Navigation Menu install).
				await page
					.locator(
						'#navigationMenuItemsGridContainer a.pkp_linkaction_addNavigationMenuItem',
					)
					.click();

				const itemForm = page.locator('form#navigationMenuItemsForm');
				await expect(itemForm).toBeVisible({timeout: 10_000});

				// Switch the menuItemType to NMI_TYPE_CUSTOM, which reveals
				// the customNMIType.tpl section (path + content fields).
				await itemForm
					.locator('select[name="menuItemType"]')
					.selectOption('NMI_TYPE_CUSTOM');
				await itemForm.locator('input[name="title[en]"]').fill(itemTitle);
				await itemForm.locator('input[name="path"]').fill(itemPath);
				await itemForm.getByRole('button', {name: 'Save'}).click();
				await expect(itemForm).toHaveCount(0, {timeout: 15_000});

				// Verify the new item lands in the items grid.
				await expect(
					page
						.locator('#navigationMenuItemsGridContainer tr.gridRow', {
							hasText: itemTitle,
						})
						.first(),
				).toBeVisible({timeout: 15_000});

				// --- Edit ---
				await clickRowAction(
					page,
					'#navigationMenuItemsGridContainer',
					itemTitle,
					'Edit',
				);
				const editForm = page.locator('form#navigationMenuItemsForm');
				await expect(editForm).toBeVisible({timeout: 10_000});
				await editForm.locator('input[name="title[en]"]').fill(updatedTitle);
				await editForm.getByRole('button', {name: 'Save'}).click();
				await expect(editForm).toHaveCount(0, {timeout: 15_000});

				await expect(
					page
						.locator('#navigationMenuItemsGridContainer tr.gridRow', {
							hasText: updatedTitle,
						})
						.first(),
				).toBeVisible({timeout: 15_000});

				// --- Delete ---
				await clickRowAction(
					page,
					'#navigationMenuItemsGridContainer',
					updatedTitle,
					'Remove',
				);
				await page.getByRole('button', {name: 'OK'}).click();
				await expect(
					page.locator('#navigationMenuItemsGridContainer', {
						hasText: updatedTitle,
					}),
				).toHaveCount(0, {timeout: 15_000});
			} finally {
				await ctx.close();
			}
		},
	);
});
