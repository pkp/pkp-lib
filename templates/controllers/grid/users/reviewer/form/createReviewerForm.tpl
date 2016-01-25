{**
 * templates/controllers/grid/users/reviewer/createReviewerForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Create a reviewer and assign to a submission form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#createReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler',
			{ldelim}
				fetchUsernameSuggestionUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component="api.user.UserApiHandler" op="suggestUsername" firstName="FIRST_NAME_DUMMY" lastName="LAST_NAME_DUMMY" escape=false},
				usernameSuggestionTextAlert: {translate|json_encode key="grid.user.mustProvideName"},
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="createReviewerForm" method="post" action="{url op="createReviewer"}" >
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="createReviewerFormNotification"}

	<div class="action_links">
		{foreach from=$reviewerActions item=action}
			{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
		{/foreach}
	</div>

	<h3>{translate key="editor.review.createReviewer"}</h3>

	{if count($userGroups)>1}
		{fbvFormSection title="user.group"}
			{fbvElement type="select" name="userGroupId" id="userGroupId" from=$userGroups translate=false label="editor.review.userGroupSelect" required="true"}
		{/fbvFormSection}
	{elseif count($userGroups)==1}
		{foreach from=$userGroups key=userGroupId item=userGroupName}
			{fbvElement type="hidden" id="userGroupId" value=$userGroupId}
		{/foreach}
	{/if}

	{fbvFormSection title="common.name"}
		{fbvElement type="text" label="user.firstName" id="firstName" value=$firstName required="true" inline=true size=$fbvStyles.size.SMALL}
		{fbvElement type="text" label="user.middleName" id="middleName" value=$middleName inline=true size=$fbvStyles.size.SMALL}
		{fbvElement type="text" label="user.lastName" id="lastName" value=$lastName required="true" inline=true size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormSection description="user.register.usernameRestriction"}
		{fbvElement type="text" label="user.username" id="username" value=$username required="true" size=$fbvStyles.size.MEDIUM inline=true}
		{fbvElement type="button" label="common.suggest" id="suggestUsernameButton" inline=true class="default"}
	{/fbvFormSection}

	{fbvFormSection title="about.contact"}
		{fbvElement type="text" label="user.email" id="email" required="true" value=$email maxlength="90" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="manager.reviewerSearch.interests" for="interests"}
		{fbvElement type="interests" id="interests" interests=$interests}
	{/fbvFormSection}

	{fbvFormSection}
		{fbvElement type="text" multilingual="true" name="affiliation" id="affiliation" value=$affiliation label="user.affiliation" inline=true size=$fbvStyles.size.LARGE}
	{/fbvFormSection}

	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	{fbvFormButtons submitText="editor.submission.addReviewer"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
