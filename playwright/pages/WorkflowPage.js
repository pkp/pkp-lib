// @ts-check
/**
 * @file lib/pkp/playwright/pages/WorkflowPage.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The per-submission workflow screen — the same Vue page, side menu, panels and
 * decision wizard in OJS, OMP and OPS, which is why the POM is shared while the
 * feature suites that use it are not.
 *
 * Two facts about the screen drive everything here:
 *
 * 1. **The workflow screen is a modal.** It renders as a reka-ui dialog inside
 *    the dashboard, and `[data-cy="active-modal"]` marks only the TOPMOST open
 *    dialog. While a sub-dialog is open (a decision's revision choice, a file
 *    wizard, a legacy grid modal) the workflow screen no longer carries the
 *    attribute — so `activeModal` always means "the dialog the user is looking
 *    at", and a helper that needs the workflow screen back waits for its own
 *    landmark rather than for a modal count.
 * 2. **The round's status is a heading plus its sibling paragraphs**
 *    (`WorkflowSubmissionStatus.vue`), which is why `statusLines()` reads the
 *    paragraphs FOLLOWING the heading instead of guessing at a container class.
 *
 * Nothing here names an app, a stage id or an app-specific label: callers pass
 * the context path and the labels their own app's suite asserts on.
 */

const {expect} = require('@playwright/test');
const {BasePage} = require('./BasePage.js');

exports.WorkflowPage = class WorkflowPage extends BasePage {
	/**
	 * @param {import('@playwright/test').Page} page
	 * @param {string} contextPath urlPath of the context the submission lives in
	 */
	constructor(page, contextPath) {
		super(page);
		this.contextPath = contextPath;

		/** The dialog currently on top — the workflow screen, or a sub-dialog over it. */
		this.activeModal = page.locator('[data-cy="active-modal"]').last();
		this.primaryItems = this.activeModal.locator('[data-cy="workflow-primary-items"]');
		this.actionItems = this.activeModal.locator('[data-cy="workflow-action-items"]');
		this.secondaryItems = this.activeModal.locator('[data-cy="workflow-secondary-items"]');
	}

	//
	// Opening the screen
	//

	/**
	 * The editorial view of a submission (editors, managers, assistants).
	 *
	 * @param {number} submissionId
	 */
	async gotoEditorial(submissionId) {
		this.workflowUrl = `/index.php/${this.contextPath}/dashboard/editorial?workflowSubmissionId=${submissionId}`;
		await this.page.goto(this.workflowUrl);
		await this.waitForOpen();
	}

	/**
	 * The author's own view of the same submission.
	 *
	 * @param {number} submissionId
	 */
	async gotoAuthor(submissionId) {
		this.workflowUrl = `/index.php/${this.contextPath}/dashboard/mySubmissions?workflowSubmissionId=${submissionId}`;
		await this.page.goto(this.workflowUrl);
		await this.waitForOpen();
	}

	/** The workflow column's own heading is the landmark that the screen is up. */
	async waitForOpen() {
		await expect(this.workflowHeading).toBeVisible({timeout: 40_000});
	}

	/**
	 * "Workflow: Review (Round 1)" and friends — the workflow column's own
	 * heading, rendered from the screen's `#heading` slot ahead of every panel.
	 * Panels in the side column head themselves at the same level (the
	 * "Recommendation" listing does), so position, not level alone, picks it out.
	 */
	get workflowHeading() {
		return this.activeModal.getByRole('heading', {level: 2}).first();
	}

	//
	// Side menu
	//

	/**
	 * A side-menu entry (a stage, or "Review Round N").
	 *
	 * @param {string} label
	 */
	menuItem(label) {
		return this.activeModal.getByRole('treeitem', {name: label, exact: true});
	}

	/**
	 * Select a review round and wait for the workflow column to follow.
	 *
	 * The heading carries the round number in every app ("Workflow: Review
	 * (Round 2)" / "Workflow: External Review (Round 2)"), so that is what the
	 * wait keys on — the surrounding wording is the app suite's to assert.
	 *
	 * @param {number} round
	 */
	async openRound(round) {
		await this.menuItem(`Review Round ${round}`).click();
		await expect(this.workflowHeading).toHaveText(new RegExp(`\\(Round ${round}\\)`));
	}

	//
	// Status
	//

	/**
	 * The status box's heading — "Round N Status" on the current round, plain
	 * "Status" on an earlier one.
	 *
	 * @param {string} name
	 */
	statusHeading(name) {
		return this.activeModal.getByRole('heading', {level: 3, name, exact: true});
	}

	/**
	 * The status paragraphs under that heading, in order. A journal that sets a
	 * minimum number of confirmed reviews adds a prompt line above the status, so
	 * this is deliberately a list rather than one string.
	 *
	 * @param {string} name heading name
	 */
	statusLines(name) {
		return this.statusHeading(name).locator('xpath=following-sibling::p');
	}

	//
	// Panels
	//

	/**
	 * A panel's heading ("Files for Review", "Reviewers", "Notifications", ...).
	 *
	 * @param {string} title
	 */
	panel(title) {
		return this.activeModal.getByRole('heading', {level: 3, name: title, exact: true});
	}

	/**
	 * A panel's table, which carries the panel title as its accessible name.
	 *
	 * @param {string} title
	 */
	panelTable(title) {
		return this.activeModal.getByRole('table', {name: title, exact: true});
	}

	//
	// Actions
	//

	/**
	 * A decision / action button in the workflow's action column.
	 *
	 * @param {string} name
	 */
	action(name) {
		return this.actionItems.getByRole('button', {name, exact: true});
	}

	/**
	 * Record a decision end to end: press its button, answer the revision-variant
	 * question when the decision asks one, then walk the wizard's steps.
	 *
	 * The wizard has a variable number of steps per decision (Notify Authors,
	 * Select Files, Notify Reviewers), so the walk is driven by which button the
	 * step offers rather than by a step count.
	 *
	 * @param {string} label the action button's label
	 * @param {object} [options]
	 * @param {'stayInRound'|'newRound'} [options.revisions] answer for Request Revisions
	 * @param {(page: import('@playwright/test').Page) => Promise<void>} [options.onStep] runs on every wizard step before it is advanced
	 */
	async recordDecision(label, {revisions, onStep} = {}) {
		await this.action(label).click();

		if (revisions) {
			// Requesting or recommending revisions asks first, in a side modal,
			// whether the revisions go to a new round; every other decision
			// navigates straight away. Both forms list stay-in-round first and
			// preselect it (SelectRevision*Form), so the choice is by position —
			// the decision constants behind them differ per form.
			const choice = this.activeModal;
			const options = choice.locator('input[type="radio"]');
			await expect(options).toHaveCount(2);
			await options.nth(revisions === 'newRound' ? 1 : 0).check();
			await choice.getByRole('button', {name: 'Next', exact: true}).click();
		}

		await this.page.waitForURL(/\/decision\/record\//, {waitUntil: 'commit'});
		await this.walkDecisionWizard({onStep});
	}

	/**
	 * Advance through the decision wizard until the decision is recorded.
	 *
	 * @param {object} [options]
	 * @param {(page: import('@playwright/test').Page) => Promise<void>} [options.onStep]
	 */
	async walkDecisionWizard({onStep} = {}) {
		const record = this.page.getByRole('button', {name: 'Record Decision', exact: true});
		const next = this.page.getByRole('button', {name: 'Continue', exact: true});

		for (let step = 0; step < 6; step++) {
			await expect(record.or(next).first()).toBeVisible({timeout: 30_000});
			await this.awaitEmailTemplateLoaded();

			if (onStep) {
				await onStep(this.page);
			}

			if (await record.isVisible()) {
				await record.click();

				// Recording ends in a success dialog offering a way back to the
				// submission; the workflow screen is reopened from its own URL so
				// the caller lands where it started whatever that dialog offers.
				await expect(
					this.page.locator('[role="dialog"] a[href*="workflowSubmissionId="]').first(),
				).toBeVisible({timeout: 60_000});

				await this.page.goto(this.workflowUrl);
				await this.waitForOpen();

				return;
			}

			await next.click();
		}

		throw new Error('The decision wizard never offered "Record Decision".');
	}

	/**
	 * The composer steps fetch their email template over AJAX and mask the body
	 * meanwhile; submitting through the mask posts an empty body and the server
	 * rejects it.
	 */
	async awaitEmailTemplateLoaded() {
		const mask = this.page.locator('.composer__loadingTemplateMask');

		if (await mask.count()) {
			await expect(mask.first()).toBeHidden({timeout: 30_000});
		}
	}

	//
	// File wizard
	//

	/**
	 * Drive the legacy three-step file wizard ("Upload File" → "Review Details"
	 * → "Confirm") that every file panel's Upload button opens.
	 *
	 * The component must be chosen BEFORE the file is handed over: the upload
	 * widget validates the component server-side as the file lands, and a file
	 * dropped first fails with "Missing or invalid component!".
	 *
	 * @param {string} filePath
	 * @param {object} [options]
	 * @param {string} [options.component] Article Component / Monograph Component label
	 */
	async completeFileWizard(filePath, {component = 'Article Text'} = {}) {
		const wizard = this.activeModal;

		await expect(wizard.getByRole('combobox').first()).toBeVisible({timeout: 30_000});
		await wizard.getByRole('combobox').first().selectOption({label: component});
		await wizard.locator('input[type="file"]').setInputFiles(filePath);

		const next = wizard.getByRole('button', {name: 'Continue', exact: true});
		await expect(next).toBeEnabled({timeout: 40_000});
		await next.click();

		// Step 2 (Review Details) keeps the same Continue button; step 3 confirms.
		await expect(
			wizard.getByRole('button', {name: 'Complete', exact: true}).or(next).first(),
		).toBeVisible({timeout: 30_000});

		if (await next.isVisible()) {
			await next.click();
		}

		const complete = wizard.getByRole('button', {name: 'Complete', exact: true});
		await expect(complete).toBeVisible({timeout: 30_000});
		await complete.click();
	}
};
