{**
 * templates/dashboard/column-stage.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The Stage column of the submissions table.
 *}
<td is="table-cell" class="submissions__list__item__stage">
	<stage-bubble :stage-id="submission.stageId">
		{{ submission.stageName }}
		<template v-if="
			(
				submission.stageId === {$smarty.const.WORKFLOW_STAGE_ID_INTERNAL_REVIEW}
				|| submission.stageId === {$smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW}
			)
			&& submission.reviewRounds.length
		">
			{{
				__('common.inParenthesis', {
					text: i18nReviewRound.replace(
						'{ldelim}$round{rdelim}',
						submission
							.reviewRounds[submission.reviewRounds.length - 1]
							.round
					)
				})
			}}
		</template>
	</stage-bubble>
</td>