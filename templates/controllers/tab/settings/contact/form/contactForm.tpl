{**
 * controllers/tab/settings/contact/form/contactForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Contact management form.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#contactForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="contactForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="saveFormData" tab="contact"}">
	{help file="chapter6/context/contact.md" class="pkp_helpers_align_right"}
	<div class="pkp_helpers_clear"></div>

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="contactFormNotification"}
	{include file="controllers/tab/settings/wizardMode.tpl" wizardMode=$wizardMode}

	{fbvFormArea id="contactFormArea" title="manager.setup.principalContact"}
		{fbvFormSection description="manager.setup.principalContactDescription"}
			{fbvElement type="text" label="user.name" required=true id="contactName" value=$contactName maxlength="60" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" label="user.title" multilingual=true name="contactTitle" id="contactTitle" value=$contactTitle maxlength="90" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" label="user.email" required=true id="contactEmail" value=$contactEmail maxlength="90" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" label="user.phone" id="contactPhone" value=$contactPhone maxlength="24" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="user.affiliation" for="contactAffiliation"}
			{fbvElement type="text" multilingual=true name="contactAffiliation" id="contactAffiliation" value=$contactAffiliation maxlength="90"}
		{/fbvFormSection}
	{/fbvFormArea}

	{* In wizard mode, these fields should be hidden *}
	{if $wizardMode}
		{assign var="wizardClass" value="is_wizard_mode"}
		{assign var="wizard_required" value=false}
	{else}
		{assign var="wizardClass" value=""}
		{assign var="wizard_required" value=true}
	{/if}
	{fbvFormArea id="contactFormArea" title="manager.setup.technicalSupportContact" class=$wizardClass}
		{fbvFormSection description="manager.setup.technicalSupportContactDescription"}
			{fbvElement type="text" label="user.name" required=$wizard_required id="supportName" value=$supportName maxlength="60" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" label="user.email" required=$wizard_required id="supportEmail" value=$supportEmail maxlength="60" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" label="user.phone" id="supportPhone" value=$supportPhone maxlength="24" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{if !$wizardMode}
		{fbvFormButtons id="contactFormSubmit" submitText="common.save" hideCancel=true}
	{/if}
</form>
