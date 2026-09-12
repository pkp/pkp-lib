// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Playwright port of the reviewer-assignment UI flow embedded in the
 * Cypress submission fixture specs (AmwandengaSubmission,
 * DdioufSubmission, DphillipsSubmission). Each of those specs opened the
 * workflow page and called `cy.assignReviewer(name, reviewMethod)` one or
 * more times to populate a review round; the interesting feature under
 * test here is the UI that performs that assignment, independent of
 * whatever decision flow came next.
 *
 * Flow (matches lib/pkp/cypress/support/commands.js:610):
 *   1. Seed a submission that's already in external review via
 *      `submissionInReview` but override `reviewers: []` so the test owns
 *      the first assignment (no collisions with seeded reviewers).
 *   2. As dbarnes, open the workflow page (lands on the Review stage).
 *   3. Click the "Add Reviewer" button on the ReviewerManager panel.
 *      This opens a legacy-grid side-modal hosting the
 *      `SelectReviewerListPanel` + `AddReviewerFormHandler` form.
 *   4. Pick a reviewer from the list via "Select <name>" (accessible name
 *      `common.selectWithName`).
 *   5. The form morphs to the assignment step (reviewer name + anonymity
 *      radios + due-date pair). Tweak the radio and re-read the due-date
 *      fields to confirm the form is populated.
 *   6. Click the submit "Add Reviewer" button inside the modal.
 *   7. Verify the reviewer appears in the reviewer-manager list via the
 *      REST reviewers endpoint and the DOM row.
 *
 * We keep it to a single test: the Cypress source drove the same flow
 * with only the reviewer/name varying, and the anonymity + due-date
 * coverage already lives under the one submit. Splitting buys no new
 * surface. If a future regression isolates the anonymity panel, split
 * then.
 */
test.describe('Reviewer assignment (UI)', () => {
	test('editor assigns a reviewer via Add Reviewer modal with anonymity + due dates; reviewer appears in the list', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'reviewer-assign');
		const spec = submissionInReview({
			tag,
			// Override reviewers to empty — the default fixture seeds
			// phudson + jjanssen, both of whom we'd then have to avoid
			// colliding with. An empty round lets this test own the first
			// assignment outright.
			reviewers: [],
		});
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// The ReviewerManager panel renders an "Add Reviewer" top button
		// (useReviewerManagerConfig.getTopItems → label
		// `editor.submission.addReviewer`). The button lives inside
		// data-cy="reviewer-manager".
		const reviewerManager = page.locator('[data-cy="reviewer-manager"]');
		await expect(reviewerManager).toBeVisible({timeout: 15_000});

		await reviewerManager
			.getByRole('button', {name: 'Add Reviewer', exact: true})
			.click();

		// The Add Reviewer side-modal opens as a reka-ui dialog with
		// accessible name "Add Reviewer". The whole editorial workflow
		// is itself already rendered inside a dashboard dialog, so we
		// scope by the unique dialog title. The modal hosts a legacy
		// advancedSearchReviewerForm via a PHP component URL; the search
		// list (`SelectReviewerListPanel`) renders inside it.
		const modal = page.getByRole('dialog', {
			name: 'Add Reviewer',
			exact: true,
		});
		await expect(modal).toBeVisible({timeout: 15_000});

		// The list-panel class hook `listPanel--selectReviewer` is on the
		// rendered Vue component that pkp.registry.init mounts inside the
		// modal. Wait for it to settle before interacting.
		const selectPanel = modal.locator('.listPanel--selectReviewer').last();
		await expect(selectPanel).toBeVisible({timeout: 20_000});

		// The per-item Select button is a PkpButton whose accessible
		// name expands from common.selectWithName ("Select {$name}"). The
		// aria-visible span reads only "Select Reviewer", but the
		// screen-reader span carries the reviewer name, so role+name
		// resolves the row unambiguously with no search needed.
		await modal
			.getByRole('button', {name: 'Select Paul Hudson', exact: true})
			.first()
			.click();

		// The UI morphs after selecting a reviewer: the legacy wrapper
		// `#regularReviewerForm` (present but hidden in the DOM up to
		// this point) now becomes visible. Inside it, the selected
		// reviewer name renders in `#selectedReviewerName` and the
		// assignment <form id="advancedSearchReviewerForm"> is the one
		// we submit. Stacked modal opens accumulate DOM copies of these
		// ids — scope everything to the last instance so we bind to the
		// currently-active panel.
		const regularForm = modal.locator('#regularReviewerForm').last();
		await expect(regularForm).toBeVisible({timeout: 15_000});

		// Verify the selected reviewer is echoed back in the form.
		await expect(
			regularForm.locator('#selectedReviewerName'),
		).toContainText('Paul Hudson');

		const reviewerForm = regularForm.locator('#advancedSearchReviewerForm');

		// Anonymity — pick "Anonymous Reviewer/Disclosed Author"
		// (SUBMISSION_REVIEW_METHOD_ANONYMOUS = 1). Radios render as
		// name="reviewMethod" with value="1|2|3". Using name+value is
		// more stable than id= which fbvElement suffixes with
		// $FBV_uniqId per form render.
		const anonymousRadio = reviewerForm.locator(
			'input[name="reviewMethod"][value="1"]',
		);
		await anonymousRadio.check();
		await expect(anonymousRadio).toBeChecked();

		// Due dates — the form pre-populates them from the journal's
		// numWeeksPerResponse / numWeeksPerReview config. The bootstrap
		// journal sets both to 4 so the response and review defaults
		// land on the same day; legacy form-side validation ("Review
		// due date must be greater or equal to response due date") then
		// disables the submit. Push the review-due date out by a day to
		// keep the pair strictly ordered, mirroring what an editor would
		// do in the UI when the seeded defaults collide.
		//
		// The legacy jquery-ui datepicker setup wraps each date in two
		// inputs: a visible one bound to the label, and a hidden
		// `name="..."` alt-field carrying the canonical yy-mm-dd value
		// the form posts back. Setting the visible input via fill()
		// doesn't propagate to the alt-field — that's the datepicker's
		// internal job. Drive it via $.datepicker.setDate so the alt
		// input, the displayed input, and the form's onChange handlers
		// (which gate the submit-button enable state) all observe the
		// same single source of truth.
		const responseDueHidden = reviewerForm.locator(
			'input[name="responseDueDate"]',
		);
		const reviewDueHidden = reviewerForm.locator(
			'input[name="reviewDueDate"]',
		);
		await expect(responseDueHidden).not.toHaveValue('');
		await expect(reviewDueHidden).not.toHaveValue('');
		const responseDueValue = await responseDueHidden.inputValue();
		const reviewDueValue = await reviewDueHidden.inputValue();
		if (responseDueValue === reviewDueValue) {
			const bumped = new Date(responseDueValue);
			bumped.setUTCDate(bumped.getUTCDate() + 1);
			const bumpedIso = bumped.toISOString().slice(0, 10);
			await page.evaluate((nextDate) => {
				const altField = document.querySelector(
					'input[name="reviewDueDate"]',
				);
				if (!altField) {
					throw new Error('reviewDueDate alt-field not found');
				}
				// $.datepicker.setDate updates the display input, the alt
				// field, and fires the onSelect callback the legacy form
				// uses to re-evaluate validation. Hide the picker
				// afterwards: the OJS legacy form binds focus →
				// datepicker.show, and the modal stays focused on the
				// input after setDate, so the dropdown latches open and
				// blocks the submit button until dismissed.
				const visibleId = altField.id.replace(/-altField$/, '');
				const visible = document.getElementById(visibleId);
				if (!visible) {
					throw new Error(`reviewDueDate visible input ${visibleId} not found`);
				}
				const $ = window.jQuery || window.$;
				$(visible).datepicker('setDate', nextDate);
				$(visible).trigger('change');
				$(visible).datepicker('hide');
				visible.blur();
			}, bumpedIso);
			await expect(reviewDueHidden).toHaveValue(bumpedIso);
		}

		// Submit. The form's submit button is a legacy <button> whose
		// label comes from `editor.submission.addReviewer`. Scope to the
		// form so we don't collide with the modal title / outer opener.
		// Wait for the button to be enabled — the form's date-pair
		// validator can briefly flip it off after a fill().
		const submitButton = reviewerForm.getByRole('button', {
			name: 'Add Reviewer',
			exact: true,
		});
		await expect(submitButton).toBeEnabled({timeout: 5_000});
		await submitButton.click();

		// Wait for the modal to close and the reviewer-manager to re-render
		// with the new row.
		await expect(modal).toBeHidden({timeout: 20_000});

		// DOM sanity: the new reviewer shows up inside the
		// reviewer-manager table with the name we assigned and the
		// anonymity label we picked ("Anonymous Reviewer/Disclosed
		// Author" — matches `editor.submissionReview.anonymous`, the
		// label ReviewerManagerCellReviewType renders for method=1).
		await expect(reviewerManager).toContainText('Paul Hudson', {
			timeout: 15_000,
		});
		await expect(reviewerManager).toContainText(
			'Anonymous Reviewer/Disclosed Author',
		);

		// REST round-trip: the submission's `reviewAssignments` inline
		// payload (PKPSubmissionController::get) now includes phudson
		// with reviewMethod=ANONYMOUS (1). This closes the loop on the
		// DB write — the UI's list could in principle show a stale
		// client-side optimistic row.
		const submissionAfter = await workflow.fetchSubmission(submission.id);
		const reviewAssignments = submissionAfter.reviewAssignments || [];
		expect(reviewAssignments.length).toBe(1);
		const assignment = reviewAssignments[0];
		expect(assignment.reviewerFullName).toMatch(/Paul Hudson/);
		expect(assignment.reviewMethod).toBe(1);
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
