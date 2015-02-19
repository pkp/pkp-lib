{**
 * controllers/tab/settings/information/form/informationForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Information management form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#informationForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler', {ldelim}
			baseUrl: '{$baseUrl|escape:"javascript"}'
		{rdelim});
	{rdelim});
</script>

<form id="informationForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="information"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="informationFormNotification"}

	{fbvFormArea id="information" class="border"}
		{fbvFormSection description="manager.setup.information.description"}
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.information.forReaders"}
			{fbvElement type="textarea" multilingual=true id="readerInformation" value=$readerInformation rich=true}
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.information.forAuthors"}
			{fbvElement type="textarea" multilingual=true id="authorInformation" value=$authorInformation rich=true}
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.information.forLibrarians"}
			{fbvElement type="textarea" multilingual=true id="librarianInformation" value=$librarianInformation rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons id="informationFormSubmit" submitText="common.save" hideCancel=true}
</form>
