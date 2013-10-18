{**
 * templates/controllers/grid/users/reviewer/createReviewerForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
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
				templateUrl: "{url|escape:'javascript' router=$smarty.const.ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}"
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
	{fbvFormSection title="user.group"}
		{fbvElement type="select" name="userGroupId" id="userGroupId" from=$userGroups translate=false label="editor.review.userGroupSelect" required="true"}
	{/fbvFormSection}

	{fbvFormSection description="user.register.usernameRestriction"}
		{fbvElement type="text" label="user.username" id="username" value=$username required="true" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="common.name"}
		{fbvElement type="text" label="user.firstName" id="firstname" value=$firstname required="true" inline=true size=$fbvStyles.size.SMALL}
		{fbvElement type="text" label="user.middleName" id="middlename" value=$middlename inline=true size=$fbvStyles.size.SMALL}
		{fbvElement type="text" label="user.lastName" id="lastname" value=$lastname required="true" inline=true size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormSection title="about.contact"}
		{fbvElement type="text" label="user.email" id="email" required="true" value=$email maxlength="90" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="manager.reviewerSearch.interests" for="interests"}
		{fbvElement type="interests" id="interests" interestsKeywords=$interestsKeywords interestsTextOnly=$interestsTextOnly}
	{/fbvFormSection}

	{fbvFormSection}
		{fbvElement type="text" multilingual="true" name="affiliation" id="affiliation" value=$affiliation label="user.affiliation" inline=true size=$fbvStyles.size.LARGE}
	{/fbvFormSection}



	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	{fbvFormButtons submitText="editor.submission.addReviewer"}
</form>
