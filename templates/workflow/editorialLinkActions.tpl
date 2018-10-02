{**
 * templates/workflow/editorialLinkActions.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Show editorial link actions.
 *}
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

		{* pkel/retostauffer: additional output, hide action buttons, and jquery functionality to show/hide action links *}
		{if $isActiveStage}
			<div class="pkp_workflow_decided decision_{$stageDecision}">
				{if ($submissionStatus == 1 && !empty($stageDecision))}
				{translate key="editor.submission.workflowDecisions.stageDecision"}:<br>
					<p>
						{translate key=$stageDecision}
					</p><br>
					<a href="#">{translate key="editor.submission.workflowDecisions.stageDecision.change"}</a>
				{elseif ( !empty($stageDecision) )}
					{translate key=$stageDecision}<br>
					<a href="#">{translate key="editor.submission.workflowDecisions.stageDecision.change"}</a>
				{/if}

				<ul class="pkp_workflow_decisions {if (!empty($stageDecision) || ($submissionStatus == 4 || $submissionStatus == 3))}hide_buttons{/if}">
					{* added pkel/retostauffer If a decision has been made for this state: hide the decision buttons. *}
						<script>
						$(document).ready(function() {ldelim}
							$(".pkp_workflow_decided > a").on("click",function() {ldelim}
								var theul = $(this).closest("div").children("ul");
								if ( $(theul).css("display") == "none" ) {ldelim}
									$(theul).fadeIn();
								{rdelim} else {ldelim}
									$(theul).fadeOut();
								{rdelim}	
							{rdelim});
						{rdelim});
						</script>
					{foreach from=$editorActions item=action}
						<li>
							{include file="linkAction/linkAction.tpl" action=$action contextId=$contextId}
						</li>
					{/foreach}
				</ul>
			</div>
		{* End of $isActiveStage statement *}
		{/if}
	{/if}
{elseif !$editorsAssigned && array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), (array)$userRoles)}
	<div class="pkp_no_workflow_decisions">
		{translate key="editor.submission.decision.noDecisionsAvailable"}
	</div>
{/if}
