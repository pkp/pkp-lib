{**
 * templates/user/userPasswordReset.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to reset a user's password
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="user.changePassword"}
	</h1>

	<div class="app__contentPanel">
		<script>
			$(function() {ldelim}
				// Attach the form handler.
				$('#updateResetPassword').pkpHandler('$.pkp.controllers.form.FormHandler');
			{rdelim});
		</script>

		{if !$passwordLengthRestrictionLocaleKey}
			{assign var="passwordLengthRestrictionLocaleKey" value="user.register.form.passwordLengthRestriction"}
		{/if}

		<form class="pkp_form" id="updateResetPassword" method="post" action="{url page="login" op="updateResetPassword"}">
			{csrf}
			{include file="common/formErrors.tpl"}

			<p><span class="instruct">{translate key="user.login.resetPasswordInstructions"}</span></p>

			{fbvFormArea id="loginFields"}
				{fbvFormSection label="user.profile.newPassword" for="password"}
					{fbvElement type="text" required=true password=true id="password" value=$password maxlength="32" size=$fbvStyles.size.MEDIUM}
					{fieldLabel translate=true for=password key=$passwordLengthRestrictionLocaleKey length=$minPasswordLength}
				{/fbvFormSection}
				{fbvFormSection label="user.profile.repeatNewPassword" for="password2"}
					{fbvElement type="text" required=true password=true id="password2" value=$password2|default:""|escape maxlength="32" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}

                {fbvElement type="hidden" id="username" value=$username}
                {fbvElement type="hidden" id="hash" value=$hash}

				<p>
					{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
					{translate key="user.privacyLink" privacyUrl=$privacyUrl}
				</p>

				<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
				{fbvFormButtons hideCancel=true submitText="common.save"}
			{/fbvFormArea}
		</form>
	</div>
{/block}
