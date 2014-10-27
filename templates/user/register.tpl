{**
 * templates/user/register.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User registration form.
 *}
{strip}
{assign var="pageTitle" value="user.register"}
{include file="common/header.tpl"}
{/strip}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#register').pkpHandler('$.pkp.controllers.grid.settings.user.form.UserFormHandler',
			{ldelim}
				fetchUsernameSuggestionUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_PAGE page="user" op="suggestUsername" firstName="FIRST_NAME_DUMMY" lastName="LAST_NAME_DUMMY" escape=false}',
				usernameSuggestionTextAlert: '{translate key="grid.user.mustProvideName"}'
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="register" method="post" action="{url op="registerUser"}">

<p>{translate key="user.register.completeForm"}</p>

{if !$implicitAuth}
	{if !$existingUser}
		{url|assign:"url" page="user" op="register" existingUser=1}
		<p>{translate key="user.register.alreadyRegisteredOtherContext" registerUrl=$url}</p>
	{else}
		{url|assign:"url" page="user" op="register"}
		<p>{translate key="user.register.notAlreadyRegisteredOtherContext" registerUrl=$url}</p>
		<input type="hidden" name="existingUser" value="1"/>
	{/if}

	{if $existingUser}
		<p>{translate key="user.register.loginToRegister"}</p>
	{/if}
{/if}{* !$implicitAuth *}

{if $source}
	<input type="hidden" name="source" value="{$source|escape}" />
{/if}

{fbvFormArea id="registration"}

	{if !$implicitAuth}
		{include
			file="common/userDetails.tpl"
			disableEmailSection=true
			disableAuthSourceSection=true
			disableGeneratePasswordSection=true
			disableSendNotifySection=true
			extraContentSectionUnfolded=true
			countryRequired=true
			disableNameSection=$existingUser
			disableUserNameSuggestSection=$existingUser
			disableEmailWithConfirmSection=$existingUser
			disablePasswordRepeatSection=$existingUser
			disableCountrySection=$existingUser
			disableExtraContentSection=$existingUser
		}
	{/if}

	{include file="user/userGroups.tpl"}

	{if !$implicitAuth && !$existingUser}
		{fbvFormSection label="user.sendPassword" list=true}
			{if $sendPassword}
				{fbvElement type="checkbox" id="sendPassword" value="1" label="user.sendPassword.description" checked="checked"}
			{else}
				{fbvElement type="checkbox" id="sendPassword" value="1" label="user.sendPassword.description"}
			{/if}
		{/fbvFormSection}
	{/if}

	{if !$implicitAuth && !$existingUser && $captchaEnabled}
		<li>
		{fieldLabel name="captcha" required=true key="common.captchaField" class="desc"}
		<span>
			{$reCaptchaHtml}
		</span>
		</li>
	{/if}
{/fbvFormArea}
{url|assign:"url" page="index" escape=false}
{fbvFormButtons submitText="user.register" cancelUrl=$url}

{if ! $implicitAuth}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
{/if}{* !$implicitAuth *}

<div id="privacyStatement">
{if $privacyStatement}
	<h3>{translate key="user.register.privacyStatement"}</h3>
	<p>{$privacyStatement|nl2br}</p>
{/if}
</div>

</form>
{include file="common/footer.tpl"}
