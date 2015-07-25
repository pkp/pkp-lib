{**
 * controllers/tab/settings/emailTemplates/form/emailTemplatesForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Email templates management form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#emailTemplatesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="emailTemplatesForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.PublicationSettingsTabHandler" op="saveFormData" tab="emailTemplates"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="emailTemplatesFormNotification"}

	{fbvFormSection label="manager.setup.emailHeader" for="emailHeader" description="manager.setup.emailHeaderDescription"}
		{fbvElement type="textarea" id="emailHeader" value=$emailHeader size=$fbvStyles.size.LARGE rich=true variables=$emailVariables}
	{/fbvFormSection}
	{fbvFormSection label="manager.setup.emailSignature" for="emailSignature" description="manager.setup.emailSignatureDescription"}
		{fbvElement type="textarea" id="emailSignature" value=$emailSignature size=$fbvStyles.size.LARGE rich=true variables=$emailVariables}
	{/fbvFormSection}
	{fbvFormSection label="manager.setup.emailBounceAddress" for="envelopeSender" description="manager.setup.emailBounceAddressDescription"}
		<!-- FIXME: There may be a better way to do this if statement within the fbvElement itself -->
		{if $envelopeSenderDisabled}
			{fbvElement type="text" id="envelopeSender" value=$envelopeSender maxlength="90" disabled=$envelopeSenderDisabled size=$fbvStyles.size.LARGE label="manager.setup.emailBounceAddressDisabled"}
		{else}
			{fbvElement type="text" id="envelopeSender" value=$envelopeSender maxlength="90" disabled=$envelopeSenderDisabled size=$fbvStyles.size.LARGE}
		{/if}
	{/fbvFormSection}

	{url|assign:preparedEmailsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.preparedEmails.preparedEmailsGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="preparedEmailsGridDiv" url=$preparedEmailsGridUrl}
	
	{fbvFormButtons id="emailTemplatesFormSubmit" submitText="common.save" hideCancel=true}
</form>
