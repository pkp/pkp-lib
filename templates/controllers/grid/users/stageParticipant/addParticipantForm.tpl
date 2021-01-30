{**
 * templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form that holds the stage participants list
 *
 *}

{* Help link *}
{help file="editorial-workflow" section="participants" class="pkp_help_modal"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#addParticipantForm').pkpHandler('$.pkp.controllers.grid.users.stageParticipant.form.StageParticipantNotifyHandler',
			{ldelim}
				possibleRecommendOnlyUserGroupIds: {$possibleRecommendOnlyUserGroupIds|@json_encode},
				recommendOnlyUserGroupIds: {$recommendOnlyUserGroupIds|@json_encode},
				anonymousReviewerIds: {$anonymousReviewerIds|@json_encode},
				anonymousReviewerWarning: {$anonymousReviewerWarning|@json_encode},
				anonymousReviewerWarningOk: {$anonymousReviewerWarningOk|@json_encode},
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.stageParticipant.StageParticipantGridHandler' op='fetchTemplateBody' stageId=$stageId submissionId=$submissionId escape=false},
				notChangeMetadataEditPermissionRoles: {$notPossibleEditSubmissionMetadataPermissionChange|@json_encode},
				permitMetadataEditUserGroupIds: {$permitMetadataEditUserGroupIds|@json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="addParticipantForm" action="{url op="saveParticipant"}" method="post">
	{csrf}
	<div class="pkp_helpers_clear"></div>

	{fbvFormArea id="addParticipant"}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="stageId" value="{$stageId|escape}" />
		<input type="hidden" name="userGroupId" value="{$userGroupId|escape}" />
		<input type="hidden" name="userIdSelected" value="{$userIdSelected|escape}" />
		<input type="hidden" name="assignmentId" value="{$assignmentId|escape}" />

		{if $assignmentId}
			<input type="hidden" name="userId" value="{$userIdSelected|escape}" />
			{fbvFormSection title="stageParticipants.selectedUser"}
				<b>{$currentUserName}</b> ({$currentUserGroup})
			{/fbvFormSection}

			{if $isChangeRecommendOnlyAllowed}
				{fbvFormSection title="stageParticipants.options" list="true" class="recommendOnlyWrapperNoJavascript"}
					{fbvElement type="checkbox" name="recommendOnly" id="recommendOnly" label="stageParticipants.recommendOnly" checked=$currentAssignmentRecommendOnly}
				{/fbvFormSection}
			{/if}

			{if $isChangePermitMetadataAllowed}
				{fbvFormSection title="stageParticipants.submissionEditMetadataOptions" list="true" class="submissionEditMetadataPermitNoJavascript"}
					{fbvElement type="checkbox" name="canChangeMetadata" id="canChangeMetadata" label="stageParticipants.canChangeMetadata" checked=$currentAssignmentPermitMetadataEdit}
				{/fbvFormSection}
			{/if}

			{if !$isChangePermitMetadataAllowed && !$isChangeRecommendOnlyAllowed}
				{translate key="stageParticipants.noOptionsToHandle"}
			{/if}
		{else}
			{capture assign=userSelectGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.userSelect.UserSelectGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
			{load_url_in_div id='userSelectGridContainer' url=$userSelectGridUrl}

			{fbvFormSection title="stageParticipants.options" list="true" class="recommendOnlyWrapper"}
				{fbvElement type="checkbox" name="recommendOnly" id="recommendOnly" label="stageParticipants.recommendOnly"}
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.submissionEditMetadataOptions" list="true" class="submissionEditMetadataPermit"}
				{fbvElement type="checkbox" name="canChangeMetadata" id="canChangeMetadata" label="stageParticipants.canChangeMetadata"}
			{/fbvFormSection}
		{/if}
	{/fbvFormArea}

	{if !isset($assignmentId)}
		{fbvFormArea id="notifyFormArea"}
			{fbvFormSection title="stageParticipants.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
				{fbvElement type="select" from=$templates translate=false id="template" defaultValue="" defaultLabel=""}
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.notify.message" for="message"}
				{fbvElement type="textarea" id="message" rich=true}
			{/fbvFormSection}
			<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
		{/fbvFormArea}
	{/if}
	{fbvFormButtons}
</form>
