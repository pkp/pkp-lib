{**
 * templates/dashboard/summary.tpl
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The summary panel of a submission.
 *}
<template slot="header">
	<stage-bubble :stage-id="summarySubmission.stageId">
		{{ summarySubmission.stageName }}
		<template v-if="
			(
				summarySubmission.stageId === {$smarty.const.WORKFLOW_STAGE_ID_INTERNAL_REVIEW}
				|| summarySubmission.stageId === {$smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW}
			)
			&& summarySubmission.reviewRounds.length
		">
			{{
				__('common.inParenthesis', {
					text: i18nReviewRound.replace(
						'{ldelim}$round{rdelim}',
						summarySubmission
							.reviewRounds[summarySubmission.reviewRounds.length - 1]
							.round
					)
				})
			}}
		</template>
	</stage-bubble>
	<span class="summary__id">
		{{ summarySubmission.id }}
	</span>
</template>
<h2 class="summary__authors">
	{{ summarySubmission.publications[0].authorsStringShort }}
</h2>
<div class="summary__title">
	{{ summarySubmission.publications[0].fullTitle.en }}
</div>
<panel>
	<panel-section>
		<p>{translate key="editor.submission.daysInStage"}: XX</p>
		<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
		<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
		<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
		<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
	</panel-section>
</panel>