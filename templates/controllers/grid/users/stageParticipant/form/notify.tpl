{**
 * templates/controllers/grid/users/stageParticipant/form/notify.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display a form to notify other users about this file.
 *}
<script type="text/javascript">
	// Attach the notification handler.
	$(function() {ldelim}
		$('#notifyForm').pkpHandler(
			'$.pkp.controllers.grid.users.stageParticipant.form.StageParticipantNotifyHandler',
			{ldelim}
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.stageParticipant.StageParticipantGridHandler' op='fetchTemplateBody' stageId=$stageId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>
<div id="informationCenterNotifyTab">
	<form class="pkp_form" id="notifyForm" action="{url op="sendNotification" stageId=$stageId submissionId=$submissionId escape=false}" method="post">
		{csrf}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="notifyFormNotification"}
		{fbvFormArea id="notifyFormArea"}
			<input type="hidden" name="userId" value="{$userId|escape}"/>

			{fbvFormSection title="stageParticipants.notify.startDiscussion"}
				<p>{translate key="stageParticipants.notify.startDiscussion.description" userFullName=$userFullName}</p>
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
				{fbvElement type="select" from=$templates translate=false id="template" defaultValue="" defaultLabel=""}
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.notify.message" for="message" required="true"}
				{fbvElement type="textarea" id="message" rich=true required="true"}
			{/fbvFormSection}
			<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
			{fbvFormButtons id="notifyButton" hideCancel=true submitText="submission.stageParticipants.notify"}
		{/fbvFormArea}
	</form>
</div>
