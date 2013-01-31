{**
 * controllers/tab/settings/indexing/form/indexingForm.tpl
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Indexing management form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#indexingForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="indexingForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.DistributionSettingsTabHandler" op="saveFormData" tab="indexing"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="indexingFormNotification"}
	{include file="controllers/tab/settings/wizardMode.tpl" wizardMode=$wizardMode}

	{fbvFormArea id="searchEngineIndexing"}
		{fbvFormSection title="common.description" description="manager.setup.searchEngineIndexingDescription" label="manager.setup.searchEngineIndexing"}
			{fbvElement type="text" multilingual="true" id="searchDescription" name="searchDescription" value=$searchDescription size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection title="common.keywords"}
			{fbvElement type="text" multilingual="true" id="searchKeywords" name="searchKeywords" value=$searchKeywords size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.customTags" description="manager.setup.customTagsDescription"}
			{fbvElement type="textarea" multilingual="true" id="customHeaders" name="customHeaders" value=$customHeaders}
		{/fbvFormSection}
	{/fbvFormArea}

	<div class="separator"></div>

	{if !$wizardMode}
		{fbvFormButtons id="indexingFormSubmit" submitText="common.save" hideCancel=true}
	{/if}
</form>