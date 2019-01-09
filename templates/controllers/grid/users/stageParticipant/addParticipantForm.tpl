{**
 * templates/controllers/grid/users/stageParticipant/addParticipantForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
				blindReviewerIds: {$blindReviewerIds|@json_encode},
				blindReviewerWarning: {$blindReviewerWarning|@json_encode},
				blindReviewerWarningOk: {$blindReviewerWarningOk|@json_encode},
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.stageParticipant.StageParticipantGridHandler' op='fetchTemplateBody' stageId=$stageId submissionId=$submissionId escape=false}
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
		<input type="hidden" name="userGroupId" value="" />
		<input type="hidden" name="userIdSelected" value="" />

		{capture assign=userSelectGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.userSelect.UserSelectGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
		{load_url_in_div id='userSelectGridContainer' url=$userSelectGridUrl}

		{fbvFormSection title="stageParticipants.options" list="true" class="recommendOnlyWrapper"}
			{fbvElement type="checkbox" name="recommendOnly" id="recommendOnly" label="stageParticipants.recommendOnly"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="notifyFormArea"}
		{fbvFormSection title="stageParticipants.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
			{fbvElement type="select" from=$templates translate=false id="template" defaultValue="" defaultLabel=""}
		{/fbvFormSection}

		{fbvFormSection title="stageParticipants.notify.message" for="message"}
			{fbvElement type="textarea" id="message" rich=true}
		{/fbvFormSection}
		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
		{fbvFormButtons}
	{/fbvFormArea}
</form>
