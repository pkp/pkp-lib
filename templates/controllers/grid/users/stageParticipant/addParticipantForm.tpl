{**
 * templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form that holds the stage participants list
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#addParticipantForm').pkpHandler('$.pkp.controllers.grid.users.stageParticipant.form.AddParticipantFormHandler',
			{ldelim}
				fetchUserListUrl: '{url op="fetchUserList" submissionId=$submissionId stageId=$stageId userGroupId=$selectedUserGroupId escape=false}'
			{rdelim}
		);
	{rdelim});
</script>

<p>{translate key="editor.submission.addStageParticipant.description"}</p>
<form class="pkp_form" id="addParticipantForm" action="{url op="saveParticipant"}" method="post">
	{fbvFormArea id="addParticipant"}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="stageId" value="{$stageId|escape}" />
		{fbvFormSection title="user.group"}
			{fbvElement type="select" id="userGroupId" from=$userGroupOptions translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="user.name" required="true"}
			{capture assign="defaultLabel"}{translate key="common.chooseOne"}{/capture}
			{fbvElement class="noStyling" type="select" id="userId" size=$fbvStyles.size.MEDIUM required="true" defaultValue="" defaultLabel=$defaultLabel}
		{/fbvFormSection}
		{fbvFormButtons}
	{/fbvFormArea}
</form>
