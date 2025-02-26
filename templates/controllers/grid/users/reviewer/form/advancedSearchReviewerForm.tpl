{**
 * templates/controllers/grid/user/reviewer/form/advancedSearchReviewerForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Advanced Search and assignment reviewer form.
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Handle moving the reviewer ID from the grid to the second form
		$('#advancedReviewerSearch').pkpHandler(
				'$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler',
				{ldelim}
					lastRoundReviewerIds: {$lastRoundReviewerIds|json_encode},
					reviewerMessages: {$reviewerMessages|json_encode},
					reviewerName : {$reviewerName|json_encode}
				{rdelim}
		);
	{rdelim});
</script>

<div id="advancedReviewerSearch" class="pkp_form pkp_form_advancedReviewerSearch">
	{if !isset($reviewerId) }
		<div id="searchGridAndButton">
			{assign var="uuid" value=""|uniqid|escape}
			<div id="select-reviewer-{$uuid}">
			</div>
			<script type="text/javascript">
				pkp.registry.init(
					'select-reviewer-{$uuid}',
					'AdvancedSearchReviewerContainer',
					{$selectReviewerListData|@json_encode}
				);
			</script>

			{** This button will get the reviewer selected in the grid and insert their ID into the form below **}
			{fbvFormSection class="form_buttons"}
				{foreach from=$reviewerActions item=action}
					{if $action->getId() == 'advancedSearch'}
						{continue}
					{/if}
					{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
				{/foreach}
			{/fbvFormSection}
		</div>
	{/if}

	<div id="regularReviewerForm" class="pkp_reviewer_form">
		{** Display the name of the selected reviewer **}
		<div class="selected_reviewer">
			<div class="label">
				{translate key="editor.submission.selectedReviewer"}
			</div>
			<div class="value">
				<span id="selectedReviewerName" class="name"></span>
				{if !isset($reviewerId) }
					<span class="actions">
						{foreach from=$reviewerActions item=action}
							{if $action->getId() == 'advancedSearch'}
								{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
							{/if}
						{/foreach}
					</span>
				{/if}
			</div>
		</div>

		{include file="controllers/grid/users/reviewer/form/advancedSearchReviewerAssignmentForm.tpl"}
	</div>
</div>
