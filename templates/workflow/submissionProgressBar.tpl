{**
 * templates/workflow/submissionProgressBar.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include the submission progress bar and the tab structure for the workflow.
 *}

{if $stageId > $smarty.const.WORKFLOW_STAGE_ID_INTERNAL_REVIEW}
	{assign var=selectedTabIndex value=$stageId-2}
{else}
	{assign var=selectedTabIndex value=$stageId-1}
{/if}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#stageTabs').pkpHandler(
			'$.pkp.controllers.tab.workflow.WorkflowTabHandler',
			{ldelim}
				selected: {$selectedTabIndex},
				notScrollable: true
			{rdelim}
		);
	{rdelim});
</script>
<div style="clear:both">
	<div id="stageTabs">
		<ul>
			{foreach from=$workflowStages item=stage}
				<li class="workflowStage">
					<a class="{$stage.path}" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.workflow.WorkflowTabHandler" op="fetchTab" submissionId=$submission->getId() stageId=$stage.id escape=false}">
					{translate key=$stage.translationKey}
					<div class="stageState">
							{translate key=$stage.statusKey}
						</div>
					</a>
				</li>
			{/foreach}
		</ul>
	</div>
</div>