{**
 * templates/workflow/review.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Review workflow stage.
 *}

{* Help tab *}
{help file="editorial-workflow/review" class="pkp_help_tab"}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#reviewTabs').pkpHandler(
			'$.pkp.controllers.TabHandler',
			{ldelim}
				{assign var=roundIndex value=$lastReviewRoundNumber-1}
				selected: {$roundIndex},
				disabled: [{$lastReviewRoundNumber}]
			{rdelim}
		);
	{rdelim});
</script>

{if $reviewRounds}
	<div id="reviewTabs" class="pkp_controllers_tab">
		<ul>
			{foreach from=$reviewRounds item=reviewRound}
				<li>
					<a href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.workflow.ReviewRoundTabHandler" op=$reviewRoundOp submissionId=$submission->getId() stageId=$reviewRound->getStageId() reviewRoundId=$reviewRound->getId()}">{translate key="submission.round" round=$reviewRound->getRound()}</a>
				</li>
			{/foreach}
			{if $newRoundAction}
				<li>
					{include file="linkAction/linkAction.tpl" image="add_item" action=$newRoundAction contextId="newRoundTabContainer"}
				</li>
			{/if}
		</ul>
	</div>

	{capture assign=queriesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
	{load_url_in_div id="queriesGrid" url=$queriesGridUrl}
{else}
	<p>{translate key="editor.review.notInitiated"}</p>
{/if}
</div>
