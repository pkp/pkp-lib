{**
 * templates/controllers/informationCenter/notify.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a form to notify other users about this file.
 *}
<script type="text/javascript">
	// Attach the file upload form handler.
	$(function() {ldelim}
		$('#notifyForm').pkpHandler(
			'$.pkp.controllers.informationCenter.form.InformationCenterNotifyHandler',
			{ldelim}
				templateUrl: "{url|escape:'javascript' router=$smarty.const.ROUTE_COMPONENT component="informationCenter.SubmissionInformationCenterHandler" op='fetchTemplateBody' submissionId=$submissionId}"
			{rdelim}
		);
	{rdelim});
</script>
<div id="informationCenterNotifyTab">
	<form class="pkp_form" id="notifyForm" action="{url op="sendNotification" params=$linkParams}" method="post">
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="notifyFormNotification"}
		{fbvFormArea id="notifyFormArea"}
			{if $lastEventUser}{assign var="userId" value=$lastEventUser->getId()}{/if}
			{url|assign:notifyUsersUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.users.NotifyUsersListbuilderHandler" op="fetch" params=$linkParams userId=$userId escape=false}
			{load_url_in_div id="notifyUsersContainer" url=$notifyUsersUrl}

			{fbvFormSection title="informationCenter.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
				{fbvElement type="select" from=$templates translate=false id="template" defaultValue="" defaultLabel=""}
			{/fbvFormSection}

			{fbvFormSection title="informationCenter.notify.message" for="message" required="true"}
				{fbvElement type="textarea" id="message"}
			{/fbvFormSection}
			{fbvFormButtons id="notifyButton" hideCancel=true submitText="submission.informationCenter.notify"}
		{/fbvFormArea}
	</form>
</div>
