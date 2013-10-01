{**
 * templates/user/lostPassword.tpl
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Password reset form.
 *
 *}
{strip}
{assign var="pageTitle" value="user.login.resetPassword"}
{include file="common/header.tpl"}
{/strip}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#lostPasswordForm').pkpHandler('$.pkp.controllers.form.FormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="lostPasswordForm" action="{url page="login" op="requestResetPassword"}" method="post">
<p>{translate key="user.login.resetPasswordInstructions"}</p>
{if $error}
	<p><span class="pkp_form_error">{translate key=$error}</span></p>
{/if}
{fbvFormArea id="lostPassword"}
	{fbvFormSection label="user.login.registeredEmail"}
		{fbvElement type="text" id="email" value=$username maxlength="90" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}
	{if !$hideRegisterLink}
		{url|assign:cancelUrl page="user" op="register"}
		{fbvFormButtons cancelUrl=$cancelUrl cancelText="user.login.registerNewAccount" submitText="user.login.resetPassword"}
	{else}
		{fbvFormButtons hideCancel=true submitText="user.login.resetPassword"}
	{/if}
{/fbvFormArea}
</form>

{include file="common/footer.tpl"}
