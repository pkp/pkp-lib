{**
 * templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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
				fetchUserListUrl: {url|json_encode op="fetchUserList" submissionId=$submissionId stageId=$stageId userGroupId=$selectedUserGroupId escape=false},
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.stageParticipant.StageParticipantGridHandler' op='fetchTemplateBody' stageId=$stageId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="addParticipantForm" action="{url op="saveParticipant"}" method="post">
	{fbvFormArea id="addParticipant"}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="stageId" value="{$stageId|escape}" />
		{fbvFormSection title="user.group"}
			{fbvElement type="select" id="userGroupId" label="editor.submission.addStageParticipant.userGroup" from=$userGroupOptions translate=false}
		{/fbvFormSection}
		{fbvFormSection title="user.name" required="true"}
			{capture assign="defaultLabel"}{translate key="common.chooseOne"}{/capture}
			{fbvElement class="noStyling" type="select" id="userId" size=$fbvStyles.size.MEDIUM required="true" defaultValue="" defaultLabel=$defaultLabel}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="notifyFormArea"}
		{fbvFormSection title="stageParticipants.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
			{fbvElement type="select" from=$templates translate=false id="template" defaultValue="" defaultLabel=""}
		{/fbvFormSection}

		{fbvFormSection title="stageParticipants.notify.message" for="message"}
			{fbvElement type="textarea" id="message" rich=true}
		{/fbvFormSection}
		{fbvFormButtons}
	{/fbvFormArea}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
