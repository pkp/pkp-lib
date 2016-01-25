{**
 * templates/controllers/grid/user/reviewer/form/advancedSearchReviewerFilterForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays the widgets that generate the filter sent to the reviewerSelect grid.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Handle filter form submission
		$('#reviewerFilterForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AdvancedSearchReviewerFilterFormHandler',
			{ldelim}
				trackFormChanges: false
			{rdelim}
		);
	{rdelim});
</script>

{** This form contains the inputs that will be used to filter the list of reviewers in the grid below **}
<form class="pkp_form" id="reviewerFilterForm" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.reviewerSelect.ReviewerSelectGridHandler" op="fetchGrid"}" method="post" class="pkp_controllers_reviewerSelector">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="advancedSearchReviewerFilterFormNotification"}
	{fbvFormArea id="reviewerSearchForm"}
		<input type="hidden" id="submissionId" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" id="stageId" name="stageId" value="{$stageId|escape}" />
		<input type="hidden" id="reviewRoundId" name="reviewRoundId" value="{$reviewRoundId|escape}" />
		<input type="hidden" name="clientSubmit" value="1" />

		{fbvFormSection description="manager.reviewerSearch.doneAmount" inline=true size=$fbvStyles.size.SMALL}
			<div id="completeRange"></div>
			{fbvElement type="text" id="doneMin" value=$reviewerValues.doneMin|default:0 label="search.dateFrom" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="doneMax" value=$reviewerValues.doneMax|default:100 label="search.dateTo" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection description="manager.reviewerSearch.avgAmount" inline=true size=$fbvStyles.size.SMALL}
			<div id="averageRange"></div>
			{fbvElement type="text" id="avgMin" value=$reviewerValues.avgMin|default:0 label="search.dateFrom" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="avgMax" value=$reviewerValues.avgMax|default:365 label="search.dateTo" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection description="manager.reviewerSearch.lastAmount" inline=true size=$fbvStyles.size.SMALL}
			<div id="lastRange"></div>
			{fbvElement type="text" id="lastMin" value=$reviewerValues.lastMin|default:0 label="search.dateFrom" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="lastMax" value=$reviewerValues.lastMax|default:365 label="search.dateTo" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection description="manager.reviewerSearch.activeAmount" inline=true size=$fbvStyles.size.SMALL}
			<div id="activeRange"></div>
			{fbvElement type="text" id="activeMin" value=$reviewerValues.activeMin|default:0 label="search.dateFrom" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" id="activeMax" value=$reviewerValues.activeMax|default:100 label="search.dateTo" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection description="manager.reviewerSearch.form.interests.instructions"}
			{fbvElement type="interests" id="interests" interests=$interestSearchKeywords}
		{/fbvFormSection}
		{fbvFormSection class="pkp_helpers_text_right"}
			{fbvElement type="submit" id="submitFilter" label="common.search"}
		{/fbvFormSection}
	{/fbvFormArea}
</form>
