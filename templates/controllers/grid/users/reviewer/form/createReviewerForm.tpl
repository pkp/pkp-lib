{**
 * templates/controllers/grid/users/reviewer/createReviewerForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Create a reviewer and assign to a submission form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#createReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler',
			{ldelim}
				fetchUsernameSuggestionUrl: {url|json_encode router=PKP\core\PKPApplication::ROUTE_COMPONENT component="api.user.UserApiHandler" op="suggestUsername" givenName="GIVEN_NAME_PLACEHOLDER" familyName="FAMILY_NAME_PLACEHOLDER" escape=false},
				usernameSuggestionTextAlert: {translate|json_encode key="grid.user.mustProvideName"},
				templateUrl: {url|json_encode router=PKP\core\PKPApplication::ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="createReviewerForm" method="post" action="{url op="createReviewer"}" >
	<input type="hidden" name="sitePrimaryLocale" value="{$sitePrimaryLocale|escape}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="createReviewerFormNotification"}

	<div class="action_links">
		{foreach from=$reviewerActions item=action}
			{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
		{/foreach}
	</div>

	<h3>{translate key="editor.review.createReviewer"}</h3>

	{if count($userGroups)>1}
		{fbvFormSection title="user.group" required="true"}
			{fbvElement type="select" name="userGroupId" id="userGroupId" from=$userGroups translate=false label="editor.review.userGroupSelect" required="true"}
		{/fbvFormSection}
	{elseif count($userGroups)==1}
		{foreach from=$userGroups key=userGroupId item=userGroupName}
			{fbvElement type="hidden" id="userGroupId" value=$userGroupId}
		{/foreach}
	{/if}

	{fbvFormSection title="common.name"}
		{fbvElement type="text" label="user.givenName" multilingual="true" name="givenName" id="givenName" value=$givenName maxlength="255" inline=true size=$fbvStyles.size.MEDIUM required="true"}
		{fbvElement type="text" label="user.familyName" multilingual="true" name="familyName" id="familyName" value=$familyName maxlength="255" inline=true size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="user.username" required="true"}
		{fbvElement type="text" label="user.register.usernameRestriction" id="username" required="true" value=$username size=$fbvStyles.size.MEDIUM inline=true}
		{fbvElement type="button" label="common.suggest" id="suggestUsernameButton" inline=true class="default"}
	{/fbvFormSection}

	{fbvFormSection title="user.email" required="true"}
		{fbvElement type="email" id="email" required="true" value=$email maxlength="90" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="manager.reviewerSearch.interests" for="interests"}
		{fbvElement type="interests" id="interests" interests=$interests}
	{/fbvFormSection}

	{fbvFormSection title="user.affiliation"}
		{fbvElement type="text" multilingual="true" name="affiliation" id="affiliation" value=$affiliation size=$fbvStyles.size.LARGE}
	{/fbvFormSection}

	{if $reviewerSuggestionId}
		{fbvElement type="hidden" id="reviewerSuggestionId" name="reviewerSuggestionId" value=$reviewerSuggestionId}
	{/if}

	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons submitText="editor.submission.addReviewer"}

</form>
