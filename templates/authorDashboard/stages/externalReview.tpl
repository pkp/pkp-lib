{**
 * templates/authorDashboard/stages/externalReview.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the external review stage on the author dashboard.
 *}
{if array_key_exists($smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, $accessibleWorkflowStages)}
	<div class="pkp_authorDashboard_stageContainer" id="externalReview">
		<h3><a href="#">{translate key='workflow.review.externalReview'}</a></h3>
		<div id="externalReviewContent">
			{if $stageId >= $smarty.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW && !$externalReviewRounds->wasEmpty()}
				{include file="authorDashboard/reviewRoundTab.tpl" reviewRounds=$externalReviewRounds reviewRoundTabsId="externalReviewRoundTabs" lastReviewRoundNumber=$lastReviewRoundNumber.externalReview}
			{else}
				{translate key="submission.stageNotInitiated"}
			{/if}
		</div>
	</div>
{/if}
