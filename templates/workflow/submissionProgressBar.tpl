{**
 * templates/workflow/submissionProgressBar.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include the submission progress bar and the tab structure for the workflow.
 *}
{* Calculate the selected tab index for the current stage *}
{assign var=selectedTabIndex value=0}
{foreach from=$workflowStages item=stage}
	{if $stage.id < $stageId}
		{assign var=selectedTabIndex value=$selectedTabIndex+1}
	{/if}
{/foreach}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#stageTabs').pkpHandler(
			'$.pkp.controllers.tab.workflow.WorkflowTabHandler',
			{ldelim}
				selected: {$selectedTabIndex},
				notScrollable: true,
				emptyLastTab: true
			{rdelim}
		);
	{rdelim});
</script>
<div style="clear:both">
	<div id="stageTabs" class="pkp_controllers_tab">
		<ul>
			{foreach from=$workflowStages item=stage}
				<li class="workflowStage">
					<a class="{$stage.path} stageId{$stage.id}" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.workflow.WorkflowTabHandler" op="fetchTab" submissionId=$submission->getId() stageId=$stage.id escape=false}">
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
