{**
 * controllers/tab/settings/contextIndexing/form/contextIndexingForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Indexing management form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#contextIndexingForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="contextIndexingForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.DistributionSettingsTabHandler" op="saveFormData" tab="indexing"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="contextIndexingFormNotification"}
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

	<h4>{translate key="manager.setup.registerForIndexing"}</h4>
	{url|assign:"oaiUrl" router=$smarty.const.ROUTE_PAGE page="oai"}
	{url|assign:"siteUrl" router=$smarty.const.ROUTE_PAGE page="index"}
	<p>{translate key="manager.setup.registerForIndexingDescription" oaiUrl=$oaiUrl siteUrl=$siteUrl}</p>

	{if !$wizardMode}
		{fbvFormButtons id="contextIndexingFormSubmit" submitText="common.save" hideCancel=true}
	{/if}
</form>
