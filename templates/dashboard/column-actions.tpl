{**
 * templates/dashboard/column-actions.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The Actions column of the submissions table.
 *}
<td is="table-cell">
	<pkp-button
		class="submissions__list__item__view"
		:aria-describedby="'submission-title-' + submission.id"
		:is-link="true"
		@click="openSummary(submission)"
	>
		{translate key="editor.submission.viewSummary"}
	</pkp-button>
</td>