// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');

/**
 * Reviewer-recommendation customisation — row #6 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/ReviewerRecommendation.cy.js.
 *
 * Covers config-only concerns (defaults render, CRUD custom, toggle
 * active). Skips the two tests that require an existing reviewer
 * assignment ("used recommendation can't be edited" and "inactive
 * recommendation not offered in review form") — those need a
 * submission in review which is a different scenario state; they'll
 * be covered by row #28 Reviewer completes review when E1/E2 land.
 */

const DEFAULT_RECOMMENDATIONS = [
	'Accept Submission',
	'Revisions Required',
	'Resubmit for Review',
	'Resubmit Elsewhere',
	'Decline Submission',
	'See Comments',
];

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `rr-w${workerIndex}-${suffix}`;
}

async function openReviewerRecommendations(page, journalPath) {
	await page.goto(`/index.php/${journalPath}/management/settings/workflow`);
	await page.locator('#review-button').click();
	await page
		.getByRole('tab', {name: 'Reviewer Recommendations', exact: true})
		.click();
	await expect(
		page.locator('[data-cy="reviewer-recommendation-manager"]'),
	).toBeVisible();
}

test.describe('Reviewer-recommendation customisation', () => {
	test(
		'defaults render and have non-empty type metadata',
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
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				await openReviewerRecommendations(page, context.path);

				const manager = page.locator(
					'[data-cy="reviewer-recommendation-manager"]',
				);
				await expect(manager.locator('tbody > tr')).toHaveCount(
					DEFAULT_RECOMMENDATIONS.length,
				);
				for (const name of DEFAULT_RECOMMENDATIONS) {
					await expect(
						manager.locator('tr', {hasText: name}),
					).toBeVisible();
				}

				// Open Edit on a default that has a type — verify the
				// type select is populated.
				await manager
					.locator('tr', {hasText: 'Accept Submission'})
					.getByRole('button', {name: /More Actions/i})
					.click();
				await page.getByRole('menuitem', {name: 'Edit'}).click();
				const editModal = page.locator('[data-cy="active-modal"]');
				await expect(
					editModal.locator('select[name="type"]'),
				).not.toBeEmpty();
				await editModal
					.getByRole('button', {name: 'Close'})
					.click({force: true});
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'manager adds a custom recommendation, edits it, and deletes it',
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
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				await openReviewerRecommendations(page, context.path);

				const manager = page.locator(
					'[data-cy="reviewer-recommendation-manager"]',
				);

				// --- Add ---
				await manager
					.getByRole('button', {name: 'Add Recommendation'})
					.click();
				const addModal = page.locator('[data-cy="active-modal"]');
				const createTitle = `Custom rec ${tag}`;
				await addModal
					.locator('#reviewerRecommendation-title-control-en')
					.fill(createTitle);
				await addModal
					.locator('select[name="type"]')
					.selectOption({index: 0});
				await addModal.getByRole('button', {name: 'Save'}).click();

				await expect(manager.locator('tbody > tr')).toHaveCount(
					DEFAULT_RECOMMENDATIONS.length + 1,
				);
				await expect(
					manager.locator('tr', {hasText: createTitle}),
				).toBeVisible();

				// --- Edit ---
				await manager
					.locator('tr', {hasText: createTitle})
					.getByRole('button', {name: /More Actions/i})
					.click();
				await page.getByRole('menuitem', {name: 'Edit'}).click();
				const editModal = page.locator('[data-cy="active-modal"]');
				const editedTitle = `${createTitle} edited`;
				await editModal
					.locator('#reviewerRecommendation-title-control-en')
					.fill(editedTitle);
				await editModal
					.locator('select[name="type"]')
					.selectOption({index: 1});
				await editModal.getByRole('button', {name: 'Save'}).click();
				await expect(
					manager.locator('tr', {hasText: editedTitle}),
				).toBeVisible();

				// --- Delete ---
				await manager
					.locator('tr', {hasText: editedTitle})
					.getByRole('button', {name: /More Actions/i})
					.click();
				await page.getByRole('menuitem', {name: 'Delete'}).click();
				await page
					.locator(
						'[data-cy="dialog"],[role="dialog"]:has-text("Delete Recommendation")',
					)
					.last()
					.getByRole('button', {name: 'Yes'})
					.click();
				await expect(
					manager.locator('tr', {hasText: editedTitle}),
				).toHaveCount(0);
				await expect(manager.locator('tbody > tr')).toHaveCount(
					DEFAULT_RECOMMENDATIONS.length,
				);
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'manager can toggle active/inactive on an unused recommendation',
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
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				await openReviewerRecommendations(page, context.path);

				const manager = page.locator(
					'[data-cy="reviewer-recommendation-manager"]',
				);
				const row = manager.locator('tr', {hasText: 'See Comments'});
				const checkbox = row.locator('input[type="checkbox"]');
				await expect(checkbox).toBeChecked();

				// Deactivate — confirm Yes.
				await checkbox.click();
				await page
					.locator(
						'[role="dialog"]:has-text("Deactivate Reviewer Recommendation")',
					)
					.getByRole('button', {name: 'Yes'})
					.click();
				await expect(checkbox).not.toBeChecked();

				// Reactivate — confirm Yes.
				await checkbox.click();
				await page
					.locator(
						'[role="dialog"]:has-text("Activate Reviewer Recommendation")',
					)
					.getByRole('button', {name: 'Yes'})
					.click();
				await expect(checkbox).toBeChecked();
			} finally {
				await ctx.close();
			}
		},
	);
});
