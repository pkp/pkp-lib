{**
 * templates/workflow/editorialLinkActions.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Show editorial link actions.
 *}

{if (!empty($lastDecision))}
	<script type="text/javascript">
		// Expected to include the script somehow like this?
		//$(function() {ldelim}
		//	$('.pkp_workflow_decided').pkpHandler(
		//		'$.pkp.controllers.LinkAction_LinkActionHideButtons'
		//	);
		//{rdelim});
	</script>
{/if}
{if !empty($editorActions)}
	{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), (array)$userRoles)}
		<ul class="pkp_workflow_decisions">
			{if $allRecommendations}
				<li>
					<div class="pkp_workflow_recommendations">
						{translate key="editor.submission.allRecommendations.display" recommendations=$allRecommendations}
					</div>
				</li>
			{/if}
			{if $lastRecommendation}
				<li>
					<div class="pkp_workflow_recommendations">
						{translate key="editor.submission.recommendation.display" recommendation=$lastRecommendation}
					</div>
				</li>
			{/if}
		</ul>
		<div class="pkp_workflow_decided decision_{$submissionStatus}">
			{if (!empty($lastDecision))}
			<b>{translate key="editor.submission.workflowDecision.Submission"}</b><br>
				<p>{translate key=$lastDecision}</p><br>
				<a href="#" class="show_extras">{translate key="editor.submission.workflowDecision.changeDecision"}</a>
			{elseif ( !empty($lastDecision) )}
				{translate key=$lastDecision}<br>
				<a href="#" class="show_extras">{translate key="editor.submission.workflowDecision.changeDecision"}</a>
			{/if}
			{if ($editorActions|@count > 0) }
			<script>
			$(document).ready(function() {ldelim}
				$(".pkp_workflow_decided > a").on("click",function(e) {ldelim}
					e.preventDefault();
					var theul = $(this).closest("div").children("ul");
					if ( $(theul).css("display") == "none" ) {ldelim}
						$(this).switchClass("show_extras", "hide_extras");
						$(theul).fadeIn();
					{rdelim} else {ldelim}
						$(this).switchClass("hide_extras", "show_extras");
						$(theul).fadeOut();
					{rdelim}
				{rdelim});
			{rdelim});
			</script>
			<ul class="pkp_workflow_decisions {if (!empty($lastDecision))}hide_buttons{/if}">
				{foreach from=$editorActions item=action}
					<li>
						{include file="linkAction/linkAction.tpl" action=$action contextId=$contextId}
					</li>
				{/foreach}
			</ul>
			{/if}
		</div>
	{/if}
{elseif !$editorsAssigned && array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), (array)$userRoles)}
	<div class="pkp_no_workflow_decisions">
		{translate key="editor.submission.decision.noDecisionsAvailable"}
	</div>
{/if}
