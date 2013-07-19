{**
 * templates/user/login.tpl
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User login form.
 *
 *}
{strip}
{assign var="pageTitle" value="user.login"}
{include file="common/header.tpl"}
{/strip}

{if $loginMessage}
	<span class="instruct">{translate key="$loginMessage"}</span>
	<br />
	<br />
{/if}

{if $implicitAuth}
	<a id="implicitAuthLogin" href="{url page="login" op="implicitAuthLogin"}">Login</a>
{else}
	<script>
		$(function() {ldelim}
			// Attach the form handler.
			$('#signinForm').pkpHandler(
				'$.pkp.controllers.form.FormHandler',
				{ldelim}
					trackFormChanges: false
				{rdelim});
		{rdelim});
	</script>

	<form class="pkp_form" id="signinForm" method="post" action="{$loginUrl}" style="width: 400px;">
{/if}

{if $error}
	<span class="pkp_form_error">{translate key="$error" reason=$reason}</span>
	<br />
	<br />
{/if}

<input type="hidden" name="source" value="{$source|strip_unsafe_html|escape}" />

{if ! $implicitAuth}
	{fbvFormArea id="loginFields"}
		{fbvFormSection label="user.username" for="username"}
			{fbvElement type="text" id="username" value=$username maxlength="32" size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection label="user.password" for="password"}
			{fbvElement type="text" password=true id="password" value=$password maxlength="32" size=$fbvStyles.size.MEDIUM}
			<a href="{url page="login" op="lostPassword"}">{translate key="user.login.forgotPassword"}</a>
		{/fbvFormSection}
		{if $showRemember}
			{fbvFormSection list=true}
				{fbvElement type="checkbox" label="user.login.rememberUsernameAndPassword" id="remember" value="1" checked=$remember}
			{/fbvFormSection}
		{/if}{* $showRemember *}
		{if !$hideRegisterLink}
			{if $source}
				{url|assign:cancelUrl page="user" op="register" source=$source}
			{else}
				{url|assign:cancelUrl page="user" op="register"}
			{/if}
			{fbvFormButtons cancelUrl=$cancelUrl cancelText="user.login.registerNewAccount" submitText="user.login"}
		{else}
			{fbvFormButtons hideCancel=true submitText="user.login.resetPassword"}
		{/if}
	{/fbvFormArea}

{/if}{* !$implicitAuth *}

<script>
	{if $username}$("#password").focus();
	{else}$("#username").focus();{/if}
</script>
</form>

{include file="common/footer.tpl"}
