{**
 * templates/controllers/grid/user/reviewer/form/enrollExistingReviewerForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Enroll existing user and assignment reviewer form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#enrollExistingReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler',
			{ldelim}
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="enrollExistingReviewerForm" method="post" action="{url op="enrollReviewer"}" >
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
		{url|assign:autocompleteUrl op="getUsersNotAssignedAsReviewers" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}
		{fbvElement type="autocomplete" disableSync="true" required="true" autocompleteUrl=$autocompleteUrl id="userId" label="user.role.reviewer" value=$userNameString}
	{/fbvFormSection}

	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	{fbvFormButtons submitText="editor.submission.addReviewer"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
