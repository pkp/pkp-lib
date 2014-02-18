{**
 * controllers/tab/settings/submissionStage/form/submissionStageForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission stage management form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#submissionStageForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="submissionStageForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.PublicationSettingsTabHandler" op="saveFormData" tab="submissionStage"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="submissionStageFormNotification"}

	{url|assign:submissionChecklistGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.submissionChecklist.SubmissionChecklistGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="submissionChecklistGridDiv" url=$submissionChecklistGridUrl}

	<h4>{translate key="manager.setup.notifications"}</h4>
	{fbvFormArea id="notificationSettings"}
		{fbvFormSection description="manager.setup.notifications.description" list=true}
			{fbvElement type="checkbox" id="copySubmissionAckPrimaryContact" disabled=$submissionAckDisabled checked=$copySubmissionAckPrimaryContact label="manager.setup.notifications.copyPrimaryContact" inline=true}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" disabled=$submissionAckDisabled id="copySubmissionAckAddress" value=$copySubmissionAckAddress size=$fbvStyles.size.MEDIUM label="manager.setup.notifications.copySpecifiedAddress"}
		{/fbvFormSection}
		{if $submissionAckDisabled}
			{translate key="manager.setup.notifications.submissionAckDisabled"}
		{/if}
	{/fbvFormArea}

	{if !$wizardMode}
		{fbvFormButtons id="submissionStageFormSubmit" submitText="common.save" hideCancel=true}
	{/if}
</form>
