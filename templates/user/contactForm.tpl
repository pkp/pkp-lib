{**
 * templates/user/contactForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#contactForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="contactForm" method="post" action="{url op="saveContact"}">
	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="contactFormNotification"}

	{fbvFormSection}
		{if $changeEmailPending}
			<p>
				{fbvElement type="hidden" id="pendingEmail" value=$changeEmailPending}
				{translate key="user.pendingEmailChange" pendingEmail=$changeEmailPending}
				<button type="submit" class="pkp_button" name="action" value="cancelPendingEmail">{translate key="common.cancel"}</button>
			</p>
		{/if}
		{fbvElement type="email" readonly=$changeEmailPending|default:false label="user.email" id="email" value=$email size=$fbvStyles.size.MEDIUM required=true}
		{fbvElement type="textarea" label="user.signature" multilingual="true" name="signature" id="signature" value=$signature rich=true size=$fbvStyles.size.MEDIUM}
		{fbvElement type="tel" label="user.phone" name="phone" id="phone" value=$phone maxlength="24" size=$fbvStyles.size.SMALL}
		{fbvElement type="text" label="user.affiliation" multilingual="true" name="affiliation" id="affiliation" value=$affiliation size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}
	{fbvFormSection}
		{fbvElement type="textarea" label="common.mailingAddress" name="mailingAddress" id="mailingAddress" rich=true value=$mailingAddress size=$fbvStyles.size.MEDIUM}
		{fbvElement type="select" label="common.country" name="country" id="country" required=true defaultLabel="" defaultValue="" from=$countries selected=$country translate=false size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{if count($availableLocales) > 1}
		{fbvFormSection title="user.workingLanguages" list=true}
			{foreach from=$availableLocales key=localeKey item=localeName}
				{if $locales && in_array($localeKey, $locales)}
					{assign var="checked" value=true}
				{else}
					{assign var="checked" value=false}
				{/if}
				{fbvElement type="checkbox" name="locales[]" id="locales-$localeKey" value=$localeKey checked=$checked label=$localeName|escape translate=false}
			{/foreach}
		{/fbvFormSection}
	{/if}

	<p>
		{capture assign="privacyUrl"}{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about" op="privacy"}{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons hideCancel=true submitText="common.save"}
</form>
