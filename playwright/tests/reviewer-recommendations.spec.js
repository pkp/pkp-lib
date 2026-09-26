// @ts-check
const {test, expect} = require('../support/base-test.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Reviewer-recommendation customisation — row #6 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports cypress/tests/integration/ReviewerRecommendation.cy.js.
 *
 * Covers config-only concerns (defaults render, CRUD custom, toggle
 * active) plus the two scenarios that depend on a submission in review
 * with the recommendation already in use:
 *   - "Used recommendation can't be edited / deleted" — exercises the
 *     `removable` attribute on ReviewerRecommendation, which gates the
 *     row's More-Actions menu in the manager grid.
 *   - "Inactive recommendation not offered in review form" — exercises
 *     the dropdown on Step 3 of the reviewer wizard (the ReviewerForm
 *     filters recommendations by status).
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
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
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
		
		},
	);

	test(
		'manager adds a custom recommendation, edits it, and deletes it',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
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
		
		},
	);

	test(
		'manager can toggle active/inactive on an unused recommendation',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const ctx = await asUser('dbarnes');
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
		
		},
	);

	test(
		'a recommendation in use by a completed review cannot be edited or deleted',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			// Scratch journal with the manager + the editor + the reviewer
			// the seeded submission references. Without the latter two,
			// ContextUserProcessor / ReviewRoundProcessor would point at
			// users not assigned to this context's user groups.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [
					{username: 'dbarnes', roles: ['manager', 'editor']},
					{username: 'rvaca', roles: ['author']},
					{username: 'phudson', roles: ['reviewer']},
				],
			});

			// Seed a submission against the SCRATCH journal that has phudson's
			// review marked completed with recommendation 'accept'. This
			// flips the corresponding recommendation row's `removable`
			// attribute to false in the manager grid.
			const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');
			await pkpApi.createSubmission(
				submissionInReview({
					tag,
					journal: context.path,
					reviewers: [
						{
							user: 'phudson',
							method: 'anonymous',
							status: 'completed',
							recommendation: 'accept',
							comments: {
								toEditor: 'Solid submission.',
								toAuthor: 'Looks good.',
							},
						},
					],
				}),
			);

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await openReviewerRecommendations(page, context.path);

			const manager = page.locator(
				'[data-cy="reviewer-recommendation-manager"]',
			);
			// The recommendation 'accept' resolves server-side via
			// ReviewRoundProcessor::RECOMMENDATION_KEYS to the
			// reviewer.article.decision.accept default key — its English
			// title is "Accept Submission".
			const usedRow = manager.locator('tr', {hasText: 'Accept Submission'});
			await expect(usedRow).toBeVisible();

			// The DropdownActions component is rendered with `v-if="item.removable"`
			// (lib/ui-library .../ReviewerRecommendationManager.vue) — when a
			// review_assignments row references this recommendation, removable
			// is false and the entire More-Actions trigger never mounts. So
			// asserting the trigger button is absent covers Edit AND Delete.
			await expect(
				usedRow.getByRole('button', {name: /More Actions/i}),
			).toHaveCount(0);

			// Sanity: an unused row (Decline Submission, none seeded) still
			// has its More-Actions trigger.
			const unusedRow = manager.locator('tr', {
				hasText: 'Decline Submission',
			});
			await expect(
				unusedRow.getByRole('button', {name: /More Actions/i}),
			).toHaveCount(1);
		
		},
	);

	test(
		'an inactive recommendation does not appear in the review-completion form',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [
					{username: 'dbarnes', roles: ['manager', 'editor']},
					{username: 'rvaca', roles: ['author']},
					{username: 'phudson', roles: ['reviewer']},
				],
			});

			// --- Manager step: deactivate "See Comments" via the UI -------
			const managerCtx = await asUser('dbarnes');
			const managerPage = await managerCtx.newPage();
			await openReviewerRecommendations(managerPage, context.path);
			const manager = managerPage.locator(
				'[data-cy="reviewer-recommendation-manager"]',
			);
			const seeCommentsRow = manager.locator('tr', {
				hasText: 'See Comments',
			});
			const checkbox = seeCommentsRow.locator('input[type="checkbox"]');
			await expect(checkbox).toBeChecked();
			await checkbox.click();
			await managerPage
				.locator(
					'[role="dialog"]:has-text("Deactivate Reviewer Recommendation")',
				)
				.getByRole('button', {name: 'Yes'})
				.click();
			await expect(checkbox).not.toBeChecked();
		

			// --- Seed an in-review submission with phudson 'invited' ------
			// 'invited' (not 'completed') so the reviewer wizard renders Step
			// 1; we drive Steps 1+2 below to reach Step 3, where the
			// recommendation select renders.
			const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');
			const {submission} = await pkpApi.createSubmission(
				submissionInReview({
					tag,
					journal: context.path,
					reviewers: [
						{user: 'phudson', method: 'anonymous', status: 'invited'},
					],
				}),
			);

			// --- Reviewer step: drive the wizard to Step 3 ----------------
			const reviewerCtx = await asUser('phudson');
			const reviewerPage = await reviewerCtx.newPage();
			await reviewerPage.goto(
				`/index.php/${context.path}/en/reviewer/submission/${submission.id}`,
			);

			// Step 1 — privacy consent + Accept Review.
			const step1 = reviewerPage.locator('form#reviewStep1Form');
			await expect(step1).toBeVisible({timeout: 15_000});
			await step1.locator('input[name="privacyConsent"]').check();
			await Promise.all([
				reviewerPage.waitForURL(/\/reviewer\/submission\//, {
					timeout: 15_000,
				}),
				reviewerPage
					.getByRole('button', {
						name: /Accept Review, Continue to Step #2/i,
					})
					.click(),
			]);

			// Step 2 — Continue.
			const step2 = reviewerPage.locator('form#reviewStep2Form');
			await expect(step2).toBeVisible({timeout: 15_000});
			await Promise.all([
				reviewerPage.waitForURL(/\/reviewer\/submission\//, {
					timeout: 15_000,
				}),
				reviewerPage
					.getByRole('button', {name: /Continue to Step #3/i})
					.click(),
			]);

			// Step 3 — the dropdown under test.
			const step3 = reviewerPage.locator('form#reviewStep3Form');
			await expect(step3).toBeVisible({timeout: 15_000});

			const select = step3.locator('select#reviewerRecommendationId');
			await expect(select).toBeVisible();

			const optionLabels = (
				await select.locator('option').allTextContents()
			).map((s) => s.trim());

			// Active recommendations are present.
			expect(optionLabels).toEqual(
				expect.arrayContaining(['Accept Submission']),
			);
			// The disabled one is filtered out by
			// Repo::reviewerRecommendation()->getRecommendationOptions(...)
			// (withActive defaults to ACTIVE).
			expect(optionLabels).not.toContain('See Comments');
		
		},
	);
});
