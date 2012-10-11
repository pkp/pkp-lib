{**
 * templates/user/changePassword.tpl
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form to change a user's password.
 *}
{strip}
{assign var="pageTitle" value="user.changePassword"}
{url|assign:"currentUrl" page="user" op="changePassword"}
{include file="common/header.tpl"}
{/strip}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#changePassword').pkpHandler('$.pkp.controllers.form.FormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="changePassword" method="post" action="{url op="savePassword"}">

<p><span class="instruct">{translate key="user.profile.changePasswordInstructions"}</span></p>

{fbvFormArea id="changePasswordForm"}
	{fbvFormSection label="user.profile.oldPassword"}
		{fbvElement type="text" password="true" id="oldPassword" value=$oldPassword|escape maxLength="32" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}
	{fbvFormSection label="user.profile.newPassword"}
		{translate|assign:"passwordLengthRestriction" key="user.register.passwordLengthRestriction" length=$minPasswordLength}
		{fbvElement type="text" password="true" id="password" value=$oldPassword|escape label=$passwordLengthRestriction subLabelTranslate=false maxLength="32" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" password="true" id="password2" value=$oldPassword|escape maxLength="32" label="user.profile.repeatNewPassword" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{url|assign:cancelUrl page="user" op="profile"}
	{fbvFormButtons submitText="common.save" cancelUrl=$cancelUrl}
{/fbvFormArea}

</form>
{include file="common/footer.tpl"}
