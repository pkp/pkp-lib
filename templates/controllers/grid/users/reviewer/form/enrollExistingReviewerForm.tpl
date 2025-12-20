{**
 * templates/controllers/grid/user/reviewer/form/enrollExistingReviewerForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Enroll existing user and assignment reviewer form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#enrollExistingReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler',
			{ldelim}
				templateUrl: {url|json_encode router=PKP\core\PKPApplication::ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="enrollExistingReviewerForm" method="post" action="{url op="enrollReviewer"}" >
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="enrollExistingReviewerFormNotification"}

	<div class="action_links">
		{foreach from=$reviewerActions item=action}
			{include file="linkAction/linkAction.tpl" action=$action contextId="enrollExistingReviewerForm"}
		{/foreach}
	</div>

	<h3>{translate key="editor.review.enrollReviewer"}</h3>

	{fbvFormSection}
		{fbvElement type="select" name="userGroupId" id="userGroupId" from=$userGroups translate=false label="editor.review.userGroupSelect" required="true"}
	{/fbvFormSection}

	{fbvFormSection}
		{capture assign=autocompleteUrl}{url op="getUsersNotAssignedAsReviewers" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{fbvElement
			type="autocomplete"
			disableSync="true"
			required="true"
			autocompleteUrl=$autocompleteUrl
			id="userId"
			value="{$selectedUser|default:''|escape}"
			autocompleteValue="{$userId|default:''}"
			label="manager.reviewerSearch.searchByName.short"
		}
	{/fbvFormSection}

	{fbvFormSection title="manager.setup.masthead" list=true}
		{fbvElement type="checkbox" id="masthead" checked=true label="invitation.masthead.show" translate="true" disabled="true"}
	{/fbvFormSection}

	{if $reviewerSuggestionId}
		{fbvElement type="hidden" id="reviewerSuggestionId" name="reviewerSuggestionId" value=$reviewerSuggestionId}
	{/if}

	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons submitText="editor.submission.addReviewer"}
</form>
