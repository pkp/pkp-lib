{**
 * templates/workflow/review.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
			{rdelim}
		);
	{rdelim});
</script>

{if $reviewRounds}
	<div id="reviewTabs" class="pkp_controllers_tab">
		<ul>
			{foreach from=$reviewRounds item=reviewRound}
				<li>
					<a href="{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT component="tab.workflow.ReviewRoundTabHandler" op=$reviewRoundOp submissionId=$submission->getId() stageId=$reviewRound->getStageId() reviewRoundId=$reviewRound->getId()}">{translate key="submission.round" round=$reviewRound->getRound()}</a>
				</li>
			{/foreach}
			{if $newRoundUrl}
				<li>
					<a href="{$newRoundUrl}" id="newReviewRoundButton">{translate key="editor.submission.newRound"}</a>
					<script type="text/javascript">
						$('#newReviewRoundButton').click(function() {
							window.location($(this).attr('href'));
						});
					</script>
				</li>
			{/if}
		</ul>
	</div>

	{capture assign=queriesGridUrl}{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
	{load_url_in_div id="queriesGrid" url=$queriesGridUrl}
{else}
	<p>{translate key="editor.review.notInitiated"}</p>
{/if}
</div>
