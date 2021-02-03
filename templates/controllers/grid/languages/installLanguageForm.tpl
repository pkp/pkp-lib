{**
 * controllers/grid/languages/installLanguageForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to install languages.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#installLanguageForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="installLanguageForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.languages.AdminLanguageGridHandler" op="saveInstallLocale"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="installLanguageFormNotification"}

	{fbvFormArea id="availableLocalesFormArea" title="admin.languages.availableLocales"}
		{fbvFormSection list="true" description="admin.languages.installNewLocalesInstructions"}
			{foreach name=locales from=$notInstalledLocales item=locale}
				{fbvElement type="checkbox" id="locale-$locale" name="localesToInstall[$locale]" value=$locale label=$allLocales.$locale translate=false}
			{foreachelse}
				<p>{translate key="admin.languages.noLocalesAvailable"}</p>
			{/foreach}
		{/fbvFormSection}
	{/fbvFormArea}

	{if not empty($notInstalledLocales)}
		{fbvFormButtons id="installLanguageFormSubmit" submitText="common.save"}
	{/if}
</form>
