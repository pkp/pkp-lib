{**
 * templates/controllers/grid/user/reviewer/form/advancedSearchReviewerForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Advanced Search and assignment reviewer form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Handle moving the reviewer ID from the grid to the second form
		$('#advancedReviewerSearch').pkpHandler('$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler');
	{rdelim});
</script>

<div id="advancedReviewerSearch" class="pkp_form_advancedReviewerSearch">
	<div class="action_links">
		{foreach from=$reviewerActions item=action}
			{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
		{/foreach}
	</div>

	<div id="searchGridAndButton">
		{** The grid that will display reviewers.  We have a JS handler for handling selections of this grid which will update a hidden element in the form below **}
		{url|assign:reviewerSelectGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.users.reviewerSelect.ReviewerSelectGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}
		{load_url_in_div id='reviewerSelectGridContainer' url=$reviewerSelectGridUrl}

		{** This button will get the reviewer selected in the grid and insert their ID into the form below **}
		{fbvFormSection class="pkp_helpers_text_right"}
			{fbvElement type="button" id="selectReviewerButton" label="editor.submission.selectReviewer"}
		{/fbvFormSection}
		<br />
	</div>

	<div id="regularReviewerForm">
		{** Display the name of the selected reviewer so the user knows their button click caused an action **}
		{fbvFormSection title="editor.submission.selectedReviewer"}
			{fbvElement id="selectedReviewerName" type="text" disabled=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		<br />

		{include file="controllers/grid/users/reviewer/form/advancedSearchReviewerAssignmentForm.tpl"}
	</div>
</div>
