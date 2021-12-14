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

{assign var="uuid" value=""|uniqid|escape}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#enrollExistingReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler',
			{ldelim}
				templateUrl: {url|json_encode router=\PKP\core\PKPApplication::ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}
			{rdelim}
		);
		pkp.registry.init('non-reviewer-autocomplete-{$uuid}', 'Container', {ldelim}
			userIds: [],
			userMapper: function(user) {ldelim}
				return new Option(user.fullName + ' (' + user.email + ')', user.id);
			{rdelim}
		{rdelim});
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
		<non-reviewer-autocomplete id="non-reviewer-autocomplete-{$uuid}">
			<input type="hidden" name="userId" :value="userIds.length ? userIds[0].value : null">
			<field-mapped-autosuggest
				:selected="userIds"
				:api-url="'{$autocompleteApiUrl|escape}'"
				:label="'{{translate|escape key="manager.reviewerSearch.searchByName.short"}}'"
				:is-required="true"
				:min-input-length="2"
				:data-mapper="userMapper"
				deselect-label="Remove {ldelim}$item{rdelim}"
				selected-label="Selected"
				:max-selected-items="1"
			/>
		</non-reviewer-autocomplete>
	{/fbvFormSection}

	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons submitText="editor.submission.addReviewer"}
</form>
