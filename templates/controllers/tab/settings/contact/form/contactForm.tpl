{**
 * controllers/tab/settings/contact/form/contactForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="contactFormNotification"}
	{include file="controllers/tab/settings/wizardMode.tpl" wizardMode=$wizardMode}

	{fbvFormArea id="contactFormArea" title="manager.setup.principalContact" class="border"}
		{fbvFormSection description="manager.setup.principalContactDescription"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvFormSection for="contactName" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" label="user.name" required=true id="contactName" value=$contactName maxlength="60"}
			{/fbvFormSection}
			{fbvFormSection for="contactTitle" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" label="user.title" multilingual=true name="contactTitle" id="contactTitle" value=$contactTitle maxlength="90"}
			{/fbvFormSection}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvFormSection for="contactEmail" size=$fbvStyles.size.MEDIUM inline="true"}
				{fbvElement type="text" label="user.email" required=true id="contactEmail" value=$contactEmail maxlength="90"}
			{/fbvFormSection}
			{fbvFormSection for="contactPhone" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" label="user.phone" id="contactPhone" value=$contactPhone maxlength="24"}
			{/fbvFormSection}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvFormSection for="contactFax" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" label="user.fax" id="contactFax" value=$contactFax maxlength="24"}
			{/fbvFormSection}
		{/fbvFormSection}
		{fbvFormSection title="user.affiliation" for="contactAffiliation"}
			{fbvElement type="text" multilingual=true name="contactAffiliation" id="contactAffiliation" value=$contactAffiliation maxlength="90"}
		{/fbvFormSection}
		{fbvFormSection title="common.mailingAddress" for="contactMailingAddress"}
			{fbvElement type="textarea" multilingual=true name="contactMailingAddress" id="contactMailingAddress" value=$contactMailingAddress rich=true}
		{/fbvFormSection}
	{/fbvFormArea}

	<div {if $wizardMode}class="pkp_form_hidden"{/if}>
		{fbvFormArea id="contactFormArea" title="manager.setup.technicalSupportContact" class="border"}
			{fbvFormSection description="manager.setup.technicalSupportContactDescription"}
			{/fbvFormSection}
			{fbvFormSection title="user.name" for="supportName" required=true inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" id="supportName" value=$supportName maxlength="60"}
			{/fbvFormSection}
			{fbvFormSection title="user.email" for="supportEmail" required=true inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" id="supportEmail" value=$supportEmail maxlength="90"}
			{/fbvFormSection}
			{fbvFormSection title="user.phone" for="supportPhone" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" id="supportPhone" value=$supportPhone maxlength="24"}
			{/fbvFormSection}
		{/fbvFormArea}
	</div>

	{if !$wizardMode}
		{fbvFormButtons id="contactFormSubmit" submitText="common.save" hideCancel=true}
	{/if}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
