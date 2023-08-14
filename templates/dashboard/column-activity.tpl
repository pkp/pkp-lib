{**
 * templates/dashboard/column-activity.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The Activity column of the submissions table.
 *}
<td is="table-cell">
	<pkp-button
		v-if="isManager && needsEditors(submission)"
		@click="openAssignParticipant(submission)"
	>
		{translate key="submission.list.assignEditor"}
	</pkp-button>
	<template v-else>
		TODO
	</template>
</td>