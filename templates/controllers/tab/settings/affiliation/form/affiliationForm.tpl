{**
 * controllers/tab/settings/affiliation/form/affiliationForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Contact management form.
 *
 *}

{* Help Link *}
{help file="settings.md" section="context" class="pkp_help_tab"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#affiliationForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="affiliationForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="saveFormData" tab="affiliationAndSupport"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="affiliationFormNotification"}
	{fbvFormArea id="sponsorsContainer"}
		{fbvFormSection}
			{url|assign:sponsorGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.sponsor.sponsorGridHandler" op="fetchGrid" escape=false}
			{load_url_in_div id="sponsorGridDiv" url=$sponsorGridUrl}
		{/fbvFormSection}
		{fbvFormSection title="manager.setup.sponsors.note"}
			{fbvElement type="textarea" multilingual=true id="sponsorNote" value=$sponsorNote rich=true}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormArea id="contributorsContainer"}
		{fbvFormSection}
			{url|assign:contributorGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.contributor.ContributorGridHandler" op="fetchGrid" escape=false}
			{load_url_in_div id="contributorGridDiv" url=$contributorGridUrl}
		{/fbvFormSection}
		{fbvFormSection title="manager.setup.contributors.note"}
			{fbvElement type="textarea" multilingual=true id="contributorNote" value=$contributorNote rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons id="affiliationFormSubmit" submitText="common.save" hideCancel=true}
</form>
