// @ts-check
const {expect} = require('@playwright/test');
const {BasePage} = require('./BasePage.js');
const {setTinyMceContent} = require('../support/tinymce.js');

/**
 * POM for the Discussion Manager — a Vue component that ships in
 * lib/ui-library/src/managers/DiscussionManager and renders inside the
 * editorial workflow page across OJS/OMP/OPS. Shared lives here.
 *
 * Shape: the manager itself is a table; each row is a discussion or a
 * task; each row opens either the Add form (for create) or the Display
 * modal (for view/edit/close/start/complete). All modals render as
 * `role=dialog` with the legacy `[data-cy="dialog"]` hook still on
 * confirmation dialogs.
 *
 * Public methods return small sub-POMs (FormModal / DisplayModal) so
 * test code composes cleanly:
 *
 *     const dm = new DiscussionManagerPage(page);
 *     const form = await dm.openAdd();
 *     await form.fillTitle('Hello');
 *     await form.save();
 *     await dm.expectInGroup('Hello', 'In progress');
 */
exports.DiscussionManagerPage = class DiscussionManagerPage extends BasePage {
	static GROUPS = ['Yet to begin', 'In progress', 'Closed'];

	/** @param {import('@playwright/test').Page} page */
	constructor(page) {
		super(page);
		this.root = page.locator('[data-cy="discussion-manager"]');
	}

	/**
	 * Locator for the table row that contains the given item title.
	 * The title renders inside a `<button>` (see DiscussionManagerCellName.vue)
	 * so getByRole('button', {name: title}) → closest row is the most
	 * resilient path. Fall back to `:has()` CSS to walk back up to the
	 * `<tr>`.
	 * @param {string} title
	 */
	row(title) {
		return this.root.locator('tr').filter({
			has: this.page.getByRole('button', {name: title, exact: true}),
		});
	}

	async expectVisible() {
		await expect(this.root).toBeVisible();
	}

	async expectGroupsVisible() {
		for (const group of DiscussionManagerPage.GROUPS) {
			await expect(
				this.root.locator('th[scope="rowgroup"]').filter({hasText: group}),
			).toBeVisible();
		}
	}

	/**
	 * Click the "Add" button and wait for the form modal to render.
	 * @returns {Promise<DiscussionFormModal>}
	 */
	async openAdd() {
		await this.root.getByRole('button', {name: 'Add', exact: true}).click();
		const modal = new DiscussionFormModal(this.page);
		await modal.waitReady();
		return modal;
	}

	/**
	 * Open the view/display modal for an existing row.
	 * @param {string} title
	 * @returns {Promise<DiscussionDisplayModal>}
	 */
	async openByTitle(title) {
		await this.root.getByRole('button', {name: title, exact: true}).click();
		const modal = new DiscussionDisplayModal(this.page, title);
		await modal.waitReady();
		// The display modal fires a useFetch for the full workItem on
		// mount and shows a Spinner while it's in flight. The fetch
		// completing triggers refreshFormData, which resets display-
		// mode UI state (incl. showNewMessageField) — so interactions
		// before it finishes race the reset. Wait for the spinner to
		// go away.
		await modal.waitForLoaded();
		return modal;
	}

	/**
	 * Open the per-row More Actions menu and click a named item
	 * ("Edit" | "Delete"). For Edit, returns the FormModal that opens;
	 * for Delete, caller still needs to confirm via the OK dialog (see
	 * confirmDelete()).
	 * @param {string} title
	 * @param {'Edit'|'Delete'} action
	 * @returns {Promise<DiscussionFormModal|null>}
	 */
	async openActions(title, action) {
		await this.row(title)
			.getByRole('button', {name: 'More Actions'})
			.click();
		await this.page.getByRole('menuitem', {name: action, exact: true}).click();
		if (action === 'Edit') {
			const modal = new DiscussionFormModal(this.page);
			await modal.waitReady();
			return modal;
		}
		return null;
	}

	async confirmDelete() {
		// The confirmation dialogs carry `data-cy="dialog"` (PkpDialog).
		// Scoping by that avoids matching the ambient side-modals or
		// the outer workflow dialog — all of which also expose role=dialog.
		await this.page
			.locator('[data-cy="dialog"]')
			.getByRole('button', {name: 'OK', exact: true})
			.click();
	}

	async confirmReopen() {
		await this.page
			.locator('[data-cy="dialog"]')
			.getByRole('button', {name: 'Yes', exact: true})
			.click();
	}

	/**
	 * Assert an item is rendered under the given status group by walking
	 * from the item's row up to the nearest preceding `th[scope="rowgroup"]`
	 * header and checking its text.
	 * @param {string} title
	 * @param {'Yet to begin'|'In progress'|'Closed'} group
	 */
	async expectInGroup(title, group) {
		// The row-group header is a separate <tr> containing a
		// th[scope="rowgroup"] above the item row in the same <tbody>.
		// Poll instead of single-shot — after state-change actions
		// (close/reopen, start/complete), the store refetches the list
		// asynchronously so the item may briefly appear under the old
		// group before moving.
		const row = this.row(title);
		await expect(row).toBeVisible();
		await expect
			.poll(
				async () =>
					row.evaluate((rowEl) => {
						let el = rowEl.previousElementSibling;
						while (el) {
							const header = el.querySelector('th[scope="rowgroup"]');
							if (header) return header.textContent.trim();
							el = el.previousElementSibling;
						}
						return null;
					}),
				{timeout: 10_000, message: `"${title}" should be under group "${group}"`},
			)
			.toContain(group);
	}

	async expectActionsMenuVisible(title) {
		await expect(
			this.row(title).getByRole('button', {name: 'More Actions'}),
		).toBeVisible();
	}

	async expectActionsMenuHidden(title) {
		// Assert the row exists first so a missing row doesn't silently
		// pass a "count == 0" check.
		await expect(this.row(title)).toBeVisible();
		await expect(
			this.row(title).getByRole('button', {name: 'More Actions'}),
		).toHaveCount(0);
	}

	async expectRowCheckboxDisabled(title) {
		// Task rows render two checkboxes (close + started); discussion
		// rows render just the close one. Assert every checkbox in the
		// row is disabled — covers both shapes.
		const checkboxes = this.row(title).locator('input[type="checkbox"]');
		const count = await checkboxes.count();
		expect(count, 'row should have at least one checkbox').toBeGreaterThan(0);
		for (let i = 0; i < count; i++) {
			await expect(checkboxes.nth(i)).toBeDisabled();
		}
	}

	/**
	 * Toggle the first (close/reopen) checkbox on the row. Discussions
	 * only have this one; tasks would also have a "started" checkbox,
	 * but the reopen-via-checkbox flow is discussion-only. The `<input>`
	 * is `sr-only`, so Playwright can't click it directly — click the
	 * wrapping label instead.
	 * @param {string} title
	 */
	async toggleRowCheckbox(title) {
		await this.row(title)
			.locator('label:has(input[type="checkbox"])')
			.first()
			.click();
	}
};

/**
 * Sub-POM for the Add/Edit form modal. Form fields use OJS's predictable
 * `#discussionForm-{name}-control` ids where possible; the TinyMCE host
 * is the same but driven via `setTinyMceContent` since the editor is an
 * iframe.
 */
class DiscussionFormModal {
	/** @param {import('@playwright/test').Page} page */
	constructor(page) {
		this.page = page;
		// ModalManager tags the top-most open side-modal with
		// [data-cy="active-modal"]. Scoping to that disambiguates from
		// the outer workflow dialog (which OJS renders as a side-modal
		// too) without depending on title/label heuristics.
		this.modal = page.locator('[data-cy="active-modal"]');
		this.title = this.modal.locator('input[name="title"]');
		this.dateDue = this.modal.locator('input[name="dateDue"]');
		this.taskInfoAdd = this.modal.locator('input[name="taskInfoAdd"]');
		this.taskInfoShouldStart = this.modal.locator(
			'select[name="taskInfoShouldStart"]',
		);
	}

	async waitReady() {
		// The [data-cy="active-modal"] outer wrapper is a zero-size div
		// (reka's DialogPortal puts the actual content into an absolute
		// overlay), so `toBeVisible` on the wrapper fails even when the
		// form is fully rendered. Wait on the title input instead — it
		// only exists once the form has mounted inside the modal.
		await expect(this.title).toBeVisible();
	}

	async fillTitle(value) {
		await this.title.fill(value);
	}

	async fillDescription(html) {
		await setTinyMceContent(
			this.page,
			'discussionForm-description-control',
			html,
		);
	}

	/**
	 * Check a participant by visible full name. Looks for the label
	 * that wraps the checkbox.
	 * @param {string} fullName
	 */
	async checkParticipant(fullName) {
		const label = this.modal
			.locator('label:has(input[name="participants"])')
			.filter({hasText: fullName})
			.first();
		await label.locator('input[name="participants"]').check();
	}

	async enableTaskInfo() {
		await this.taskInfoAdd.check();
	}

	async setDateDue(yyyymmdd) {
		await this.dateDue.fill(yyyymmdd);
	}

	/**
	 * Select the responsible assignee by visible full name. Task-info
	 * radio; only visible after enableTaskInfo().
	 * @param {string} fullName
	 */
	async setResponsibleAssignee(fullName) {
		const label = this.modal
			.locator('label:has(input[name="taskInfoAssignee"])')
			.filter({hasText: fullName})
			.first();
		await label.locator('input[name="taskInfoAssignee"]').check();
	}

	/**
	 * @param {'true'|'false'} value  'true' = start immediately, 'false' = do not start yet
	 */
	async setShouldStart(value) {
		await this.taskInfoShouldStart.selectOption(value);
	}

	async save() {
		await this.modal.getByRole('button', {name: 'Save', exact: true}).click();
		// Wait for the form to detach. The [data-cy="active-modal"]
		// wrapper is zero-size so `toBeHidden` on it is unreliable —
		// check the title input has been removed from DOM instead.
		await expect(this.title).toHaveCount(0, {timeout: 10_000});
	}

	async cancel() {
		await this.modal.getByRole('button', {name: 'Cancel', exact: true}).click();
	}
}

/**
 * Sub-POM for the Display modal (shown when clicking a row title).
 */
class DiscussionDisplayModal {
	/**
	 * @param {import('@playwright/test').Page} page
	 * @param {string} title
	 */
	constructor(page, title) {
		this.page = page;
		this.title = title;
		// `data-cy="active-modal"` is set by ModalManager on the
		// currently top-most side-modal (whichever level). The display
		// modal is always the innermost one we just opened.
		this.modal = page.locator('[data-cy="active-modal"]');
	}

	async waitReady() {
		// Same concern as the form modal's waitReady — wait on actual
		// visible content inside the portal, not the zero-size wrapper.
		await expect(this.modal.getByRole('heading', {name: this.title, level: 1}))
			.toBeVisible();
	}

	/**
	 * Wait for the modal's on-mount workItem fetch to finish. The
	 * display modal renders a Spinner (`.pkpSpinner`) while loading
	 * and removes it once the data arrives — once that's gone,
	 * refreshFormData has run and display-mode state has settled.
	 */
	async waitForLoaded() {
		await expect(this.modal.locator('.pkpSpinner')).toHaveCount(0, {
			timeout: 10_000,
		});
	}

	async expectContains(text) {
		await expect(this.modal.getByText(text, {exact: false}).first()).toBeVisible();
	}

	async expectClosedLabel() {
		await expect(this.modal.getByText('Closed', {exact: false}).first())
			.toBeVisible();
	}

	async expectTaskStarted() {
		await expect(
			this.modal.getByText('Task started by', {exact: false}).first(),
		).toBeVisible();
	}

	async expectEditHidden() {
		await expect(this.modal.getByRole('button', {name: 'Edit', exact: true}))
			.toHaveCount(0);
	}

	async expectEditDisabled() {
		await expect(this.modal.getByRole('button', {name: 'Edit', exact: true}))
			.toBeDisabled();
	}

	async checkCloseThisDiscussion() {
		await this.modal
			.getByLabel('Close this Discussion', {exact: false})
			.check();
	}

	async clickAddNewMessage() {
		const btn = this.modal.getByRole('button', {
			name: 'Add New Message',
			exact: true,
		});
		await btn.click();
		// Wait for the reply field to mount. The button is bound to
		// `:is-disabled="showNewMessageField"`, so flipping to disabled
		// is the cleanest ready signal.
		await expect(btn).toBeDisabled({timeout: 5_000});
	}

	async fillReply(html) {
		// DiscussionMessages' reply FieldRichTextarea is mounted via the
		// generic component pipeline in useDiscussionManagerForm.js
		// without the `formId` prop being forwarded, so the resulting
		// controlId ends up as "-newMessage-control" (empty formId,
		// leading hyphen) rather than the documented
		// "discussionDisplay-newMessage-control". Wait on the TinyMCE
		// iframe (ends with `_ifr`) so we're robust to both shapes.
		const iframe = this.page.locator('iframe[id$="-newMessage-control_ifr"]');
		await expect(iframe).toBeVisible({timeout: 10_000});
		const iframeId = await iframe.getAttribute('id');
		if (!iframeId) {
			throw new Error('Reply TinyMCE iframe has no id');
		}
		await setTinyMceContent(this.page, iframeId.replace(/_ifr$/, ''), html);
	}

	async clickStartTask() {
		await this.modal
			.getByText('Start this task', {exact: false})
			.first()
			.click();
	}

	async clickCompleteTask() {
		await this.modal
			.getByText('Complete this task', {exact: false})
			.first()
			.click();
	}

	async save() {
		await this.modal.getByRole('button', {name: 'Save', exact: true}).click();
	}

	/**
	 * Close the display modal via the header X (DialogClose). Available
	 * regardless of write access, unlike the form's "Cancel" button
	 * which only renders for users who can edit.
	 */
	async close() {
		await this.modal.getByRole('button', {name: 'Close', exact: true}).click();
	}
}
