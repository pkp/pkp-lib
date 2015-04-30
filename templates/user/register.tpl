{**
 * templates/user/register.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User registration form.
 *}
{include file="common/header.tpl" pageTitle="user.register"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#register').pkpHandler('$.pkp.controllers.form.FormHandler',
			{ldelim}
				fetchUsernameSuggestionUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT component="api.user.UserApiHandler" op="suggestUsername" firstName="FIRST_NAME_DUMMY" lastName="LAST_NAME_DUMMY" escape=false}',
				usernameSuggestionTextAlert: '{translate key="grid.user.mustProvideName"}'
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="register" method="post" action="{url op="registerUser"}">

<p>{translate key="user.register.completeForm"}</p>

{if !$implicitAuth}
	{url|assign:"rolesProfileUrl" page="user" op="profile" path="roles"}
	{url|assign:"loginUrl" page="login" source=$rolesProfileUrl}
	<p>{translate key="user.register.alreadyRegisteredOtherContext" registerUrl=$loginUrl}</p>
{/if}{* !$implicitAuth *}

{if $source}
	<input type="hidden" name="source" value="{$source|escape}" />
{/if}

{include file="common/formErrors.tpl"}

{fbvFormArea id="registration"}
	{fbvFormArea id="userFormCompactLeft"}
		{fbvFormSection title="user.name"}
			{fbvElement type="text" label="user.firstName" required="true" id="firstName" value=$firstName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" label="user.middleName" id="middleName" value=$middleName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" label="user.lastName" required="true" id="lastName" value=$lastName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" label="user.affiliation" multilingual="true" name="affiliation" id="affiliation" value=$affiliation size=$fbvStyles.size.LARGE}
		{/fbvFormSection}

		{fbvFormSection for="username" description="user.register.usernameRestriction"}
			{fbvElement type="text" label="user.username" id="username" required="true" value=$username maxlength="32" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="button" label="common.suggest" id="suggestUsernameButton" inline=true class="default"}
		{/fbvFormSection}

		{fbvFormArea id="emailArea" class="border" title="user.email"}
			{fbvFormSection}
				{fbvElement type="text" label="user.email" id="email" value=$email size=$fbvStyles.size.MEDIUM required=true inline=true}
				{fbvElement type="text" label="user.confirmEmail" id="confirmEmail" value=$confirmEmail required=true size=$fbvStyles.size.MEDIUM inline=true}
			{/fbvFormSection}
			{if $privacyStatement}<a class="action" href="#privacyStatement">{translate key="user.register.privacyStatement"}</a>{/if}
		{/fbvFormArea}

		{fbvFormArea id="passwordSection" class="border" title="user.password"}
			{fbvFormSection for="password" class="border"}
				{fbvElement type="text" label="user.password" required=$passwordRequired name="password" id="password" password="true" value=$password maxlength="32" inline=true size=$fbvStyles.size.MEDIUM}
				{fbvElement type="text" label="user.repeatPassword" required=$passwordRequired name="password2" id="password2" password="true" value=$password2 maxlength="32" inline=true size=$fbvStyles.size.MEDIUM}
			{/fbvFormSection}

		{/fbvFormArea}

		{fbvFormSection for="country" title="common.country"}
			{fbvElement type="select" label="common.country" name="country" id="country" required=true defaultLabel="" defaultValue="" from=$countries selected=$country translate="0" size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

	{/fbvFormArea}

	{include file="user/userGroups.tpl"}

	{if $reCaptchaHtml}
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
