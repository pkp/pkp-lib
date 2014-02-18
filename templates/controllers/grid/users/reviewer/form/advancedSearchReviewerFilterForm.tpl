{**
 * templates/controllers/grid/user/reviewer/form/advancedSearchReviewerFilterForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays the widgets that generate the filter sent to the reviewerSelect grid.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Handle filter form submission
		$('#reviewerFilterForm').pkpHandler('$.pkp.controllers.form.ClientFormHandler',
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
		<input type="hidden" name="doneMin" value="0" />
		<input type="hidden" name="avgMin" value="0" />
		<input type="hidden" name="lastMin" value="0" />
		<input type="hidden" name="activeMin" value="0" />
		<input type="hidden" name="clientSubmit" value="1" />
		{fbvFormSection description="manager.reviewerSearch.form.instructions"}
			{fbvElement type="text" id="doneMax" value=$reviewerValues.doneMax label="manager.reviewerSearch.doneAmount" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="avgMax" value=$reviewerValues.avgMax label="manager.reviewerSearch.avgAmount" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="lastMax" value=$reviewerValues.lastMax label="manager.reviewerSearch.lastAmount" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" id="activeMax" value=$reviewerValues.activeMax label="manager.reviewerSearch.activeAmount" inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection description="manager.reviewerSearch.form.interests.instructions"}
			{fbvElement type="interests" id="interests" interestsKeywords=$interestSearchKeywords}
		{/fbvFormSection}
		{fbvFormSection class="pkp_helpers_text_right"}
			{fbvElement type="submit" id="submitFilter" label="common.search"}
		{/fbvFormSection}
	{/fbvFormArea}
</form>
