// @ts-check
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

/**
 * Categories — row #15 in docs/e2e-playwright-migration.md.
 *
 * Ports the "categories in the submission wizard" describe block from
 * lib/pkp/cypress/tests/integration/Categories.cy.js — tests 1, 2, 3.
 * The remaining Categories.cy.js describe blocks (category CRUD
 * management; category assignment on an in-review submission) belong
 * to other rows: category management's pure-admin side is already
 * covered implicitly by using Add Category here, and the in-review
 * assignment path needs a seeded submission (Wave 3 territory).
 *
 * The feature under test: when `submitWithCategories` is true on the
 * context AND at least one Category row exists, the submission wizard's
 * "For the Editors" step renders a Categories field
 * (FieldAutosuggestPreset with a "Select Categories" modal picker).
 * When the flag is false (default) the field is absent. See
 * PKP\components\forms\submission\ForTheEditors::addCategoryField.
 *
 * Scope deviations from the Cypress source:
 *   - The Cypress "enabling categories" test flipped the flag through
 *     the Workflow > Metadata settings UI. We seed `submitWithCategories`
 *     via the ContextBuilderProcessor passthrough (same precedent as
 *     copyrightNotice — row #11) so the flag is immutable-after-create
 *     and the scratch journal can't interfere with other parallel
 *     tests via intermediate unsaved form state. Flipping via the UI
 *     re-renders half the page per click; this seeding is strictly
 *     about setting up the feature's precondition.
 *   - The Cypress test registered a fresh `catauthor` user for the
 *     wizard run. We substitute dbarnes (manager on the scratch
 *     journal) — role doesn't change which fields render in the wizard
 *     (ForTheEditors.addCategoryField() only checks the context flag
 *     and the category count).
 *   - "Category saved on submission after submit" is dropped. The
 *     wizard's Submit button is gated by "at least one Article Text
 *     file" (row #17 / extension E1) — same blocker as rows #10, #11,
 *     #14. We stop at "category selected and persisted into the
 *     wizard's Review panel", which proves the field-level wiring.
 *     Once E1 lands, add an end-to-end submit + assert that
 *     `publication.categoryIds` reflects the selection.
 *   - Category admin CRUD (edit/delete) is not re-tested here. This
 *     spec only exercises Add Category as a precondition to the
 *     wizard's field-rendering test.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `cat-w${workerIndex}-${suffix}`;
}

/**
 * Log dbarnes into the given scratch journal. The baseline storageState
 * is publicknowledge-scoped; scratch journals need an explicit signin.
 */
async function loginDbarnes(page, contextPath) {
	await page.goto(`/index.php/${contextPath}/en/login`);
	await page.locator('input#username').fill('dbarnes');
	await page.locator('input#password').fill('dbarnesdbarnes');
	await page.locator('form#login button').click();
	await page.waitForURL(
		(url) => !url.pathname.includes('/login'),
		{timeout: 15_000},
	);
}

/**
 * Navigate to the Categories admin and click Add Category. Fills the
 * categoryForm's title (en) + path fields and Saves, then waits for
 * the row to appear in the tree table. Locators mirror
 * lib/pkp/cypress/support/commands.js#addCategory.
 *
 * When `parent` is provided, the parent's row's "More Actions" menu is
 * opened and the "Add" menu item is clicked — this routes the form's
 * action URL through `?parentCategoryId={parent.id}` (see
 * lib/ui-library/src/managers/CategoryManager/categoryManagerStore.js
 * → getCategoryForm). There is no parent dropdown on the form itself.
 *
 * The helper assumes the Categories admin is already loaded — the
 * caller navigates to it once and reuses the page across multiple
 * adds (matches Cypress's `addCategory` usage in
 * 50-CreateCategories.cy.js, where the test stays on the page).
 */
async function addCategory(page, {title, path, parent} = {}) {
	if (parent) {
		// Open the parent row's "More Actions" ellipsis menu.
		// DropdownActions renders the trigger with aria-label =
		// common.moreActions ("More Actions"); the menu items are
		// PkpButton rendered inside headlessui's MenuItems. Action
		// labels come from getItemActions() — first item is "Add"
		// (common.add).
		const parentRow = page.locator('tr', {hasText: parent}).first();
		await expect(parentRow).toBeVisible({timeout: 15_000});
		await parentRow
			.getByRole('button', {name: 'More Actions'})
			.click();
		// MenuItems portal: headlessui's MenuItem with `as="template"`
		// renders the inner PkpButton as the actual focusable element
		// with role="menuitem". The "Add" label maps to common.add.
		await page
			.getByRole('menuitem', {name: 'Add', exact: true})
			.first()
			.click();
	} else {
		const addBtn = page.getByRole('button', {name: 'Add Category'});
		await expect(addBtn).toBeVisible({timeout: 15_000});
		await addBtn.click();
	}

	const form = page.locator('form.categories__categoryForm');
	await expect(form).toBeVisible();
	await form.locator('input[name^="title-en"]').first().fill(title);
	await form.locator('input[name^="path"]').first().fill(path);
	await form.getByRole('button', {name: 'Save'}).click();

	// Form closes once save completes; the tree re-renders with the
	// new row. categoryManagerStore.categorySaved auto-expands the
	// parent so child rows render without an extra toggle click.
	await expect(
		page.locator('tr', {hasText: title}),
	).toBeVisible({timeout: 15_000});
}

/**
 * Navigate to the Categories admin tab. Used by the nested-hierarchy
 * test, which keeps the page on the categories grid across multiple
 * `addCategory` calls (matches Cypress's 50-CreateCategories pattern).
 */
async function gotoCategoriesAdmin(page, contextPath) {
	await page.goto(
		`/index.php/${contextPath}/management/settings/context`,
	);
	await page.locator('#categories-button').click();
	await expect(
		page.getByRole('button', {name: 'Add Category'}),
	).toBeVisible({timeout: 15_000});
}

test.describe('Categories — wizard field rendering', () => {
	test(
		'categories field is hidden by default in the wizard',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();

			// E0 scratch journal without submitWithCategories — the
			// flag defaults to false (see lib/pkp/schemas/context.json).
			// No category rows either. Both preconditions for the
			// field's absence are satisfied.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({baseURL});
			try {
				const page = await ctx.newPage();
				await loginDbarnes(page, context.path);

				const wizard = new SubmissionWizardPage(page, context.path);
				await wizard.goto();
				await wizard.start({title: `No-cats ${tag}`});

				// Walk to the "For the Editors" step: step 1 Upload
				// Files, step 2 Details, step 3 Contributors. Continue
				// freely — no field in these steps is required by
				// default on a scratch journal.
				await wizard.continueStep();
				await wizard.continueStep();
				await wizard.continueStep();

				// Now on "For the Editors". ForTheEditors.addCategoryField
				// bails early when !submitWithCategories, so no
				// `categoryIds` control renders. Assert both the field
				// wrapper and the "Select Categories" button are absent.
				// Scope to the current step wrapper to avoid false
				// negatives from later-rendered Review-panel copies.
				const forEditorsStep = page.locator(
					'.pkpSteps__step--current, .pkpStep:not([style*="display: none"])',
				);
				await expect(
					page.getByRole('button', {name: 'Select Categories'}),
				).toHaveCount(0);
				await expect(
					page.locator('label[for^="forTheEditors-categoryIds-"]'),
				).toHaveCount(0);

				// Advance to Review and re-check — Cypress asserted on
				// the review panel too (no Categories header).
				await wizard.continueStep();
				await expect(
					page.locator(
						'.submissionWizard__reviewPanel__item__header',
						{hasText: 'Categories'},
					),
				).toHaveCount(0);
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'enabling categories exposes the wizard field and selections appear in Review',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const categoryLabel = `Test Category ${tag}`;
			const categoryPath = `cat-${tag.slice(-8).toLowerCase()}`;

			// E0 scratch journal with submitWithCategories=true seeded
			// via the ContextBuilderProcessor passthrough. Same
			// precedent as copyrightNotice (row #11); the field still
			// needs at least one category to render, which we create
			// below via the Categories admin.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
				submitWithCategories: true,
			});

			const ctx = await browser.newContext({baseURL});
			try {
				const page = await ctx.newPage();
				await loginDbarnes(page, context.path);

				// Create a single category via the admin UI. One's
				// enough — the field renders once categories->count()
				// is > 0, and the picker's behaviour (multi-select +
				// tree expansion) isn't the feature this spec owns.
				await gotoCategoriesAdmin(page, context.path);
				await addCategory(page, {
					title: categoryLabel,
					path: categoryPath,
				});

				const wizard = new SubmissionWizardPage(page, context.path);
				await wizard.goto();
				await wizard.start({title: `With-cats ${tag}`});

				await wizard.continueStep();
				await wizard.continueStep();
				await wizard.continueStep();

				// On "For the Editors". The Categories field renders
				// as a FieldAutosuggestPreset with a "Select Categories"
				// button that opens a tree-picker modal.
				const selectBtn = page.getByRole('button', {
					name: 'Select Categories',
				});
				await expect(selectBtn).toBeVisible({timeout: 15_000});
				await selectBtn.click();

				// Modal: tick the category's checkbox by clicking its
				// label. Multiple `[data-cy="active-modal"]` nodes may
				// coexist (the category-manager modal left a hidden
				// wrapper from the earlier Add-Category flow); scope
				// to the one containing a "Select Categories" heading
				// so we aim at the open picker specifically. Playwright's
				// toBeVisible() returns false for the outer wrapper
				// because its `visibility: hidden` (side-modal open-
				// transition state) overrides inner content, so we
				// anchor on the heading being visible rather than the
				// modal root and interact directly.
				const selectModal = page
					.locator('[data-cy="active-modal"]')
					.filter({
						has: page.getByRole('heading', {
							name: 'Select Categories',
						}),
					});
				await expect(
					selectModal.getByRole('heading', {
						name: 'Select Categories',
					}),
				).toBeVisible({timeout: 15_000});
				await selectModal
					.locator('label', {hasText: categoryLabel})
					.first()
					.click();
				await selectModal
					.getByRole('button', {name: 'Save'})
					.click();
				// Modal body detaches once Save emits — wait for the
				// heading to go away rather than the outer wrapper,
				// same reason as above.
				await expect(
					page.getByRole('heading', {name: 'Select Categories'}),
				).toHaveCount(0, {timeout: 10_000});

				// Back on the For the Editors step, the chosen
				// category appears as the field's current value
				// (FieldAutosuggestPreset renders selected items as
				// removable chips). Scope to the field wrapper.
				const categoriesField = page.locator(
					'[id*="categoryIds"]',
				).first();
				await expect(categoriesField).toBeVisible();

				// Advance to Review — the review panel lists the
				// Categories field with the selected label as its
				// value. This is the load-bearing assertion: without
				// a round-trip through the wizard's autosave + the
				// API, the Review panel would render the stale value.
				await wizard.continueStep();
				const categoriesReviewItem = page
					.locator('.submissionWizard__reviewPanel')
					.filter({
						has: page.locator(
							'.submissionWizard__reviewPanel__item__header',
							{hasText: 'Categories'},
						),
					});
				await expect(categoriesReviewItem).toBeVisible();
				await expect(categoriesReviewItem).toContainText(
					categoryLabel,
				);
				// End-to-end Submit + assert-on-submission dropped
				// pending file-upload (E1 / row #17). See spec header.
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		// Row #15 graduate (per
		// docs/e2e-playwright-migration.md). Ports the nested-hierarchy
		// half of cypress/tests/data/10-ApplicationSetup/50-CreateCategories.cy.js,
		// which the original Categories.cy.js wizard tests (above) did
		// not cover. Two patterns exercised here that the basic case
		// doesn't:
		//   1. parent → child → grandchild creation. The form has no
		//      parent dropdown — child rows are created by clicking
		//      the parent row's "More Actions" → "Add" entry, which
		//      routes the form action through `?parentCategoryId={id}`
		//      (see categoryManagerStore.js → getCategoryForm).
		//   2. multiple siblings under the same parent (Engineering
		//      under Applied Science alongside Computer Science).
		// Wizard-side assertion uses the breadcrumb that
		// PKPSubmissionHandler binds to the wizard via
		// `Repo::category()->getBreadcrumbs($categories)` — the
		// review panel renders the grandchild as
		// "Applied Science > Computer Science > Computer Vision" via
		// the `common.categorySeparator` template ("{$parent} > {$child}").
		'manager creates a nested category hierarchy (parent → child → grandchild) and selects the grandchild during submission',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const pathSuffix = tag.slice(-8).toLowerCase();
			// Unique titles so parallel workers don't collide on the
			// "tr contains text" locator. Path values must also be
			// unique because the categories table has a UNIQUE
			// constraint on (context_id, path); E0 gives us a fresh
			// context_id but parallel runs of *this* spec under the
			// same worker would clash without the suffix.
			const grandparent = `Applied Science ${tag}`;
			const parent = `Computer Science ${tag}`;
			const grandchild = `Computer Vision ${tag}`;
			const sibling = `Engineering ${tag}`;

			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
				submitWithCategories: true,
			});

			const ctx = await browser.newContext({baseURL});
			try {
				const page = await ctx.newPage();
				await loginDbarnes(page, context.path);

				// Build the hierarchy. The Categories admin stays
				// loaded across all four adds — categoryManagerStore
				// auto-expands a parent after a child save, so each
				// subsequent "Add via parent's More Actions" finds
				// the latest parent row already rendered.
				await gotoCategoriesAdmin(page, context.path);
				await addCategory(page, {
					title: grandparent,
					path: `applied-science-${pathSuffix}`,
				});
				await addCategory(page, {
					title: parent,
					path: `comp-sci-${pathSuffix}`,
					parent: grandparent,
				});
				await addCategory(page, {
					title: grandchild,
					path: `computer-vision-${pathSuffix}`,
					parent: parent,
				});
				await addCategory(page, {
					title: sibling,
					path: `eng-${pathSuffix}`,
					parent: grandparent,
				});

				// All four rows exist in the grid. The tree renders
				// children only when the parent is expanded; the
				// store auto-expands ancestors after each save, so
				// the four rows are all visible at this point.
				for (const title of [
					grandparent,
					parent,
					grandchild,
					sibling,
				]) {
					await expect(
						page.locator('tr', {hasText: title}).first(),
					).toBeVisible();
				}

				// Hierarchy assertion: the grandchild's name cell
				// uses an indent style with `padding-inline-start`
				// proportional to depth (CategoryManagerCellName.vue).
				// Depth is 1 for top-level, 2 for child, 3 for
				// grandchild — assert the grandchild's depth-keyed
				// indent is greater than the grandparent's. The
				// inline style is applied to the inner span.
				const grandparentIndent = await page
					.locator('tr', {hasText: grandparent})
					.first()
					.locator('span[style*="padding-inline-start"]')
					.first()
					.evaluate((el) => el.style.paddingInlineStart);
				const grandchildIndent = await page
					.locator('tr', {hasText: grandchild})
					.first()
					.locator('span[style*="padding-inline-start"]')
					.first()
					.evaluate((el) => el.style.paddingInlineStart);
				const px = (s) => parseFloat(s);
				expect(px(grandchildIndent)).toBeGreaterThan(
					px(grandparentIndent),
				);

				// Run the wizard as the same manager and pick the
				// grandchild. The picker is the same FieldAutosuggestPreset
				// modal as the non-nested test above — it renders the
				// breadcrumb label "Applied Science ... > Computer
				// Science ... > Computer Vision ..." for the grandchild
				// (categories map keyed by id, values are full
				// breadcrumbs from Repo::category()->getBreadcrumbs).
				const wizard = new SubmissionWizardPage(
					page,
					context.path,
				);
				await wizard.goto();
				await wizard.start({title: `Nested-cats ${tag}`});
				await wizard.continueStep();
				await wizard.continueStep();
				await wizard.continueStep();

				const selectBtn = page.getByRole('button', {
					name: 'Select Categories',
				});
				await expect(selectBtn).toBeVisible({timeout: 15_000});
				await selectBtn.click();

				const selectModal = page
					.locator('[data-cy="active-modal"]')
					.filter({
						has: page.getByRole('heading', {
							name: 'Select Categories',
						}),
					});
				await expect(
					selectModal.getByRole('heading', {
						name: 'Select Categories',
					}),
				).toBeVisible({timeout: 15_000});

				// The picker renders the tree as autosuggest items.
				// Click the grandchild's label specifically (substring
				// match on the unique tag is safe — only the grandchild
				// row carries `Computer Vision ${tag}`). The breadcrumb
				// formatting in the picker uses the same getBreadcrumbs
				// output, but the visible label still contains the
				// leaf title.
				await selectModal
					.locator('label', {hasText: grandchild})
					.first()
					.click();
				await selectModal
					.getByRole('button', {name: 'Save'})
					.click();
				await expect(
					page.getByRole('heading', {
						name: 'Select Categories',
					}),
				).toHaveCount(0, {timeout: 10_000});

				// Advance to Review and assert the breadcrumb appears.
				// The full path is the load-bearing assertion: it
				// proves the grandchild was selected (not a sibling
				// or its parent), and it proves the wizard binding
				// resolves the breadcrumb via
				// `Repo::category()->getBreadcrumbs` server-side.
				await wizard.continueStep();
				const categoriesReviewItem = page
					.locator('.submissionWizard__reviewPanel')
					.filter({
						has: page.locator(
							'.submissionWizard__reviewPanel__item__header',
							{hasText: 'Categories'},
						),
					});
				await expect(categoriesReviewItem).toBeVisible();
				// `common.categorySeparator` = "{$parent} > {$child}".
				// Three levels means the rendered breadcrumb is:
				//   "{grandparent} > {parent} > {grandchild}".
				await expect(categoriesReviewItem).toContainText(
					`${grandparent} > ${parent} > ${grandchild}`,
				);
				// And the sibling Engineering must NOT have leaked
				// into the selection — defensive guard against the
				// picker accidentally selecting a parent or sibling.
				await expect(categoriesReviewItem).not.toContainText(
					sibling,
				);
			} finally {
				await ctx.close();
			}
		},
	);
});
