{**
 * templates/dashboard/column-title.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The title column of the submissions table.
 *}
<td
	is="table-cell"
	class="submissions__list__item__title"
	:id="'submission-title-' + submission.id"
	:is-row-header="true"
>
	<span class="submissions__list__item__author">
		{{ submission.publications[0].authorsStringShort }}
	</span>
	<template v-if="submission.publications[0].authorsStringShort">—</template>
	{{ submission.publications[0].fullTitle.en }}
</td>