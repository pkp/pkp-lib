{**
 * templates/controllers/grid/languages/addLanguageForm.tpl
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to add languages.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#addLanguageForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="addLanguageForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.languages.SubmissionLanguageGridHandler" op="addLanguages"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="installLanguageFormNotification"}

	{fbvFormArea id="availableLocalesFormArea" title="admin.languages.availableLocales"}
		{fbvFormSection list="true" description="manager.language.submission.form.description"}
			{foreach $availableLocales as $locale => $name}
				{fbvElement type="checkbox" id="locale-$locale" name="localesToAdd[$locale]" value=$locale label=$name|unescape:"html" translate=false checked=in_array($locale, $addedLocales)}
			{foreachelse}
				<p>{translate key="admin.languages.noLocalesAvailable"}</p>
			{/foreach}
		{/fbvFormSection}
	{/fbvFormArea}

	{if not empty($availableLocales)}
		{fbvFormButtons id="installLanguageFormSubmit" submitText="common.save"}
	{/if}
</form>
