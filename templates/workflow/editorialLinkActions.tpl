{**
 * templates/workflow/editorialLinkActions.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show editorial link actions.
 *}

{if count($decisions) || count($recommendations)}
	{if array_intersect(array(PKP\security\Role::ROLE_ID_MANAGER, PKP\security\Role::ROLE_ID_SUB_EDITOR), (array)$userRoles)}
		<script>
			// Initialize JS handler.
			$(function() {ldelim}
				$('#editorialActions').pkpHandler(
					'$.pkp.controllers.EditorialActionsHandler',
				);
			{rdelim});
		</script>

		<div id="editorialActions" class="pkp_workflow_decisions">

			{* Editors who can take a final decision *}
			{if $makeDecision && count($decisions)}
				{if $lastDecision}
					<div class="pkp_workflow_last_decision">
						{translate key=$lastDecision}
						{if $canRecordDecision}
							<button class="pkp_workflow_change_decision">
								{translate key="editor.submission.workflowDecision.changeDecision"}
							</button>
						{/if}
					</div>
				{/if}
				{if $canRecordDecision}
					<ul class="pkp_workflow_decisions_options{if $lastDecision} pkp_workflow_decisions_options_hidden{/if}">
						{if $stageId === $smarty.const.WORKFLOW_STAGE_ID_PRODUCTION}
							<li>
								<button
									class="pkp_button pkp_button_primary"
									onClick="pkp.eventBus.$emit('open-tab', 'publication')"
								>
									{translate key="editor.submission.schedulePublication"}
								</button>
							</li>
						{/if}
						{foreach from=$decisions item=decision}
							{capture assign="class"}{strip}
								{if in_array(get_class($decision), $primaryDecisions)}
									pkp_button_primary
								{/if}
								{if in_array(get_class($decision), $warnableDecisions)}
									pkp_button_offset
								{/if}
							{/strip}{/capture}
							{capture assign="url"}{$decision->getUrl(APP\core\Application::get()->getRequest(), $currentContext, $submission, $reviewRoundId)}{/capture}
							<li>
								{if $decision->getDecision() === Decision::PENDING_REVISIONS}
									<button class="pkp_button {$class}" data-decision="{$decision->getDecision()}" data-review-round-id="{$reviewRoundId}">
										{$decision->getLabel()}
									</button>
								{else}
									<a href={$url} class="pkp_button {$class}">
										{$decision->getLabel()}
									</a>
								{/if}
							</li>
						{/foreach}
					</ul>
				{/if}

				{if $allRecommendations}
					<div class="pkp_workflow_recommendations">
						{translate key="editor.submission.allRecommendations.display" recommendations=$allRecommendations}
					</div>
				{/if}

			{* Editors who can recommend a final decision *}
			{elseif $makeRecommendation && count($recommendations)}
				{if $lastRecommendation}
					<div class="pkp_workflow_last_decision">
						{translate key="editor.submission.recommendation.display" recommendation=$lastRecommendation}
						{if $canRecordDecision}
							<button class="pkp_workflow_change_decision">
								{translate key="editor.submission.changeRecommendation"}
							</button>
						{/if}
					</div>
				{/if}
				{if $canRecordDecision}
					<ul class="pkp_workflow_decisions_options{if $lastRecommendation} pkp_workflow_decisions_options_hidden{/if}">
						{foreach from=$recommendations item=recommendation}
							{capture assign="url"}{$recommendation->getUrl(APP\core\Application::get()->getRequest(), $currentContext, $submission, $reviewRoundId)}{/capture}
							<li>
								{if $recommendation->getDecision() === Decision::RECOMMEND_PENDING_REVISIONS}
									<button class="pkp_button" data-recommendation="{$recommendation->getDecision()}" data-review-round-id="{$reviewRoundId}">
										{$recommendation->getLabel()}
									</button>
								{else}
									<a href={$url} class="pkp_button">
										{$recommendation->getLabel()}
									</a>
								{/if}
							</li>
						{/foreach}
					</ul>
				{else}
					<div class="pkp_no_workflow_decisions">
						{translate key="editor.submission.recommendation.noDecidingEditors"}
					</div>
				{/if}
			{/if}
		</div>
	{/if}
{elseif !$editorsAssigned && array_intersect(array(PKP\security\Role::ROLE_ID_MANAGER, PKP\security\Role::ROLE_ID_SUB_EDITOR), (array)$userRoles)}
	<div class="pkp_no_workflow_decisions">
		{translate key="editor.submission.decision.noDecisionsAvailable"}
	</div>
{elseif $lastDecision}
	<div class="pkp_no_workflow_decisions">
		{translate key=$lastDecision}
	</div>
{/if}
