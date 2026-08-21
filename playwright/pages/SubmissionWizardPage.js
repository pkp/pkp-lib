// @ts-check
const {expect} = require('@playwright/test');
const {BasePage} = require('./BasePage.js');
const {setTinyMceContent} = require('../support/tinymce.js');

/**
 * POM for the shared submission wizard. The wizard UI (start form + the
 * step-by-step wizard that follows) lives in lib/ui-library and is
 * identical in OJS/OMP/OPS — the sequence of steps ends in the same
 * Review step with the same submit confirmation dialog. Only the URL
 * prefix differs (contextPath).
 *
 * Built for row #10 (validation) and row #11 (copyright gate) of the
 * e2e-migration roadmap. Exposes the minimum surface those specs need:
 *
 *   - start()           Start form: pick locale, set title, accept any
 *                       configured checkbox/radio requirements, click
 *                       Begin Submission.
 *   - continueStep()    Click "Continue" on the current wizard step.
 *   - gotoStep()        Use the Steps nav to jump to any step by name.
 *   - setTitle()        Update the Details step's Title (multilingual).
 *   - clearTitle()      Same, cleared.
 *   - submit()          Click the primary Submit button + confirm the
 *                       modal. Returns once the "Submission complete"
 *                       page is visible.
 *
 * The assumption is that the spec knows when each helper is safe to
 * call — the POM doesn't try to hide the wizard's multi-step nature.
 */
exports.SubmissionWizardPage = class SubmissionWizardPage extends BasePage {
	/**
	 * @param {import('@playwright/test').Page} page
	 * @param {string} [contextPath='publicknowledge']
	 */
	constructor(page, contextPath = 'publicknowledge') {
		super(page);
		this.contextPath = contextPath;
		this.submitButton = page.getByRole('button', {
			name: /^Submit$/,
		});
	}

	/**
	 * Navigate to the Start Submission page. Every user with submit
	 * permissions on the journal lands here from the "New Submission"
	 * button in the dashboard; we skip the dashboard and go direct.
	 */
	async goto() {
		await this.page.goto(`/index.php/${this.contextPath}/submission`);
	}

	/**
	 * Fill the Start form and click Begin Submission. After this returns
	 * the wizard is mounted on step 1 (Upload Files).
	 *
	 * @param {Object} opts
	 * @param {string} opts.title             Title for the StartSubmission rich-text field.
	 * @param {string} [opts.locale='English']  Label of the submission locale radio.
	 *                                         Only matters if the journal supports 2+ locales.
	 * @param {string} [opts.section]         Label of the section radio to click (e.g. 'Articles').
	 *                                         Only matters if the journal has 2+ submittable sections.
	 */
	async start({title, locale = 'English', section}) {
		// Locale radio — only rendered when supportedSubmissionLocales >= 2.
		const localeLabel = this.page.locator('label', {hasText: locale});
		if (await localeLabel.first().isVisible().catch(() => false)) {
			await localeLabel.first().click();
		}

		// StartSubmission.title is a FieldRichText with id
		// 'startSubmission-title-control'. It's a oneline rich text —
		// TinyMCE is still in play, so route through the shared helper.
		await setTinyMceContent(
			this.page,
			'startSubmission-title-control',
			title,
		);

		// Section radio — only present when 2+ submittable sections exist.
		if (section) {
			const sectionLabel = this.page.locator('label', {hasText: section});
			if (await sectionLabel.first().isVisible().catch(() => false)) {
				await sectionLabel.first().click();
			}
		}

		// Submission checklist confirm — only rendered when the journal
		// has a submissionChecklist configured. Click whatever confirm
		// label is present; swallow if absent.
		const checklist = this.page.locator('label', {
			hasText: 'Yes, my submission meets all of these requirements.',
		});
		if (await checklist.first().isVisible().catch(() => false)) {
			await checklist.first().click();
		}

		// Privacy consent — only rendered when privacyStatement is set.
		const privacy = this.page.locator('label', {
			hasText: 'Yes, I agree to have my data collected',
		});
		if (await privacy.first().isVisible().catch(() => false)) {
			await privacy.first().click();
		}

		await this.page
			.getByRole('button', {name: 'Begin Submission'})
			.click();

		// Wait until we've left the start page and the wizard mounts.
		// The submission handler redirects to /submission?id=<id> with a
		// fragment for the current step, e.g. '#files'. Detecting the
		// '?id=' query tells us we're past the Start form; the wizard
		// itself takes a moment to Vue-hydrate.
		await this.page.waitForURL(/\/submission\?id=\d+/i, {
			timeout: 20_000,
		});
		await expect(
			this.page.locator('.submissionWizard'),
		).toBeVisible();
	}

	/**
	 * Click the wizard footer's primary Continue button. Scoped to the
	 * submission wizard footer so the (unrelated) "continue" strings
	 * anywhere else on the page can't match.
	 */
	async continueStep() {
		await this.page
			.locator('.submissionWizard__footer')
			.getByRole('button', {name: 'Continue'})
			.click();
	}

	/**
	 * Jump to a wizard step via the Steps nav (the horizontal rail of
	 * step pills at the top of the wizard). Use this to re-open an
	 * earlier step after errors were surfaced at Review.
	 *
	 * @param {string} stepName  e.g. 'Details', 'Review', 'Upload Files'
	 */
	async gotoStep(stepName) {
		await this.page
			.locator('.pkpSteps')
			.getByRole('button', {name: stepName, exact: false})
			.first()
			.click({force: true});
	}

	/**
	 * Set the Details step's Title field for a specific locale.
	 *
	 * @param {string} title
	 * @param {string} [locale='en']
	 */
	async setTitle(title, locale = 'en') {
		await setTinyMceContent(
			this.page,
			`titleAbstract-title-control-${locale}`,
			title,
		);
	}

	/**
	 * Clear the Details step's Title field for a specific locale. Useful
	 * for the validation spec, which needs to trigger a required-field
	 * error after the autosave has seeded the title from the Start form.
	 *
	 * @param {string} [locale='en']
	 */
	async clearTitle(locale = 'en') {
		await this.setTitle('', locale);
	}

	/**
	 * Check the confirmSubmission copyright checkbox. Only valid on
	 * the Review step after the journal has a copyrightNotice
	 * configured. FieldOptions doesn't attach a stable id to each
	 * option input, so scope by `name`.
	 */
	async acceptCopyright() {
		await this.page
			.locator('input[name="confirmCopyright"][type="checkbox"]')
			.first()
			.check();
	}

	/**
	 * Set the "Comments for the Editor" TinyMCE rich-text field on the
	 * "For the Editors" step. The underlying control id is
	 * `commentsForTheEditors-commentsForTheEditors-control` (form id +
	 * field id). Caller is responsible for being on the correct step.
	 *
	 * @param {string} html  HTML (plain text is fine; TinyMCE wraps it).
	 */
	async setCommentsForEditors(html) {
		await setTinyMceContent(
			this.page,
			'commentsForTheEditors-commentsForTheEditors-control',
			html,
		);
	}

	/**
	 * Open the "Change Submission Settings" modal by clicking the
	 * `-linkButton` next to the "Submitting to the ... section in ..."
	 * caption. Only rendered when the journal has 2+ supported submission
	 * locales OR 2+ submittable sections (i.e., whenever there's
	 * something to reconfigure post-start).
	 */
	async openReconfigureModal() {
		await this.page
			.locator('#submission-configuration button', {hasText: 'Change'})
			.click();
		// The modal title is "Change Submission Settings". Wait for the
		// side-modal body to render — ModalManager tags the active one
		// with [data-cy="active-modal"] like the Discussion Manager
		// flows rely on.
		await expect(
			this.page
				.locator('[data-cy="active-modal"]')
				.getByRole('heading', {name: 'Change Submission Settings'}),
		).toBeVisible();
	}

	/**
	 * Inside the open reconfigure modal, pick the locale radio whose
	 * label matches `localeLabel` (e.g. "French (Canada)"), optionally
	 * switch the section radio, then click Save. Waits for the modal to
	 * close before returning.
	 *
	 * The reconfigure form is the backend's `ReconfigureSubmission` —
	 * it renders a `FieldOptions` (radio) named `locale` and, when the
	 * journal has 2+ sections, another named `sectionId`. Labels come
	 * from the localized locale name / section title.
	 *
	 * @param {Object} opts
	 * @param {string} [opts.localeLabel]   visible radio label, e.g. 'French (Canada)'
	 * @param {string} [opts.sectionLabel]  visible radio label, e.g. 'Reviews'
	 */
	async changeReconfigureSettings({localeLabel, sectionLabel} = {}) {
		const modal = this.page.locator('[data-cy="active-modal"]');
		if (localeLabel) {
			await modal
				.locator('label', {hasText: localeLabel})
				.first()
				.click();
		}
		if (sectionLabel) {
			await modal
				.locator('label', {hasText: sectionLabel})
				.first()
				.click();
		}
		await modal.getByRole('button', {name: 'Save', exact: true}).click();
		// The modal detaches on save; the form title heading
		// disappears with it. Wait on that rather than the zero-size
		// wrapper.
		await expect(
			this.page
				.locator('[data-cy="active-modal"]')
				.getByRole('heading', {name: 'Change Submission Settings'}),
		).toHaveCount(0, {timeout: 10_000});
	}

	/**
	 * Extract the submission id from the wizard's URL. The wizard
	 * mounts at `/submission?id=<id>#<step>`; this parses the `id`
	 * query param. Returns null if we're not on a wizard URL.
	 *
	 * @returns {number|null}
	 */
	currentSubmissionId() {
		const match = this.page.url().match(/[?&]id=(\d+)/);
		return match ? Number(match[1]) : null;
	}

	/**
	 * Click the primary Submit button and confirm the modal. Returns
	 * once the "Submission complete" page is visible — the caller can
	 * then assert on it.
	 */
	async submit() {
		await this.submitButton.click();
		const dialog = this.page.getByRole('dialog');
		await expect(dialog).toBeVisible();
		await dialog.getByRole('button', {name: 'Submit'}).click();
		await expect(
			this.page.getByRole('heading', {name: 'Submission complete'}),
		).toBeVisible({timeout: 20_000});
	}
};
