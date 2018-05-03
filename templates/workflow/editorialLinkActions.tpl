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
			{foreach from=$editorActions item=action}
				<li>
					{include file="linkAction/linkAction.tpl" action=$action contextId=$contextId}
				</li>
			{/foreach}
		</ul>
	{/if}
{elseif !$editorsAssigned && array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), (array)$userRoles)}
	<div class="pkp_no_workflow_decisions">
		{translate key="editor.submission.decision.noDecisionsAvailable"}
	</div>
{/if}
