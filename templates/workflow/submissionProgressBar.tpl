{**
 * templates/workflow/submissionProgressBar.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include the submission progress bar
 *}
 
<div class="submission_progress_wrapper">
	<ul class="submission_progress pkp_helpers_flatlist">
		{foreach key=key from=$workflowStages item=stage}
			{assign var="progressClass" value=""}
			{assign var="currentClass" value=""}
			{translate|assign:"stageTitle" key="submission.noActionRequired"}
			{if $stageNotifications[$key]}
				{assign var="progressClass" value="actionNeeded"}
				{translate|assign:"stageTitle" key="submission.actionNeeded"}
			{/if}
			{if !array_key_exists($key, $accessibleWorkflowStages)}
				{assign var="progressClass" value="stageDisabled"}
			{/if}
			{if $submissionIsReady && $stage.path == $smarty.const.WORKFLOW_STAGE_PATH_PRODUCTION}
				{assign var="progressClass" value="productionReady"}
				{translate|assign:"stageTitle" key="submission.productionReady"}
			{/if}
			{if $key == $stageId}
				{assign var="currentClass" value="current"}
				{translate|assign:"stageTitle" key="submission.currentStage"}
			{/if}
			<li class="{$progressClass} {$currentClass}">
				{if array_key_exists($key, $accessibleWorkflowStages)}
					<a href="{url router=$smarty.const.ROUTE_PAGE page="workflow" op=$stage.path path=$submission->getId()}" title="{$stageTitle}">{translate key=$stage.translationKey}</a>
				{else}
					<a class="pkp_common_disabled">{translate key=$stage.translationKey}</a>
				{/if}
			</li>
		{/foreach}
	</ul>
</div>