{**
 * templates/frontend/pages/userRegister.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User registration form.
 *}
{include file="frontend/components/header.tpl" pageTitle="user.register"}

<div class="page page_register">
	<h1 class="page_title">
		{translate key="user.register"}
	</h1>

	<p>
		{translate key="user.register.completeForm"}
	</p>

	{if !$implicitAuth}
		{url|assign:"rolesProfileUrl" page="user" op="profile" path="roles"}
		{url|assign:"loginUrl" page="login" source=$rolesProfileUrl}
		<p>
			{translate key="user.register.alreadyRegisteredOtherContext" registerUrl=$loginUrl}
		</p>
	{/if}

	<script type="text/javascript">
		$(function() {ldelim}
			// Attach the form handler.
			$('#register').pkpHandler('$.pkp.controllers.form.FormHandler',
				{ldelim}
					fetchUsernameSuggestionUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component="api.user.UserApiHandler" op="suggestUsername" firstName="FIRST_NAME_DUMMY" lastName="LAST_NAME_DUMMY" escape=false},
					usernameSuggestionTextAlert: {translate|json_encode key="grid.user.mustProvideName"}
				{rdelim}
			);
		{rdelim});
	</script>

	<form class="pkp_form register" id="register" method="post" action="{url op="registerUser"}">

		{if $source}
			<input type="hidden" name="source" value="{$source|escape}" />
		{/if}

		{include file="common/formErrors.tpl"}

		<fieldset class="identity">
			<legend>
				{translate key="user.profile"}
			</legend>
			<ul class="fields">
				<li class="first_name">
					<label>
						<span class="label">
							{translate key="user.firstName"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="firstName" id="firstName" value="{$firstName|escape}" maxlength="40" required>
					</label>
				</li>
				<li class="middle_name">
					<label>
						<span class="label">
							{translate key="user.middleName"}
						</span>
						<input type="text" name="middleName" value="{$middleName|escape}" maxlength="40">
					</label>
				</li>
				<li class="last_name">
					<label>
						<span class="label">
							{translate key="user.lastName"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="lastName" id="lastName" value="{$lastName|escape}" maxlength="40" required>
					</label>
				</li>
				<li class="affiliation">
					<label>
						<span class="label">
							{translate key="user.affiliation"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="affiliation[{$currentContext->getPrimaryLocale()|escape}]" id="affiliation" value="{$affiliation|escape}" required>
					</label>
				</li>
				<li class="country">
					<label>
						<span class="label">
							{translate key="common.country"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<select name="country" id="country" required>
							<option></option>
							{html_options options=$countries selected=$country}
						</select>
					</label>
				</li>
			</ul>
		</fieldset>

		<fieldset class="login">
			<legend>
				{translate key="user.login"}
			</legend>
			<ul class="fields">
				<li class="email">
					<label>
						<span class="label">
							{translate key="user.email"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="email" id="email" value="{$email|escape}" maxlength="32" required>
					</label>
				</li>
				<li class="username">
					<label>
						<span class="label">
							{translate key="user.username"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="username" id="username" value="{$username|escape}" maxlength="32" required>
					</label>
					<button id="suggestUsernameButton" class="suggest_username">
						{translate key="common.suggest"}
					</button>
				</li>
				<li class="password">
					<label>
						<span class="label">
							{translate key="user.password"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="password" name="password" id="password" value="{$password|escape}" password="true" maxlength="32" required="$passwordRequired">
					</label>
				</li>
				<li class="password">
					<label>
						<span class="label">
							{translate key="user.repeatPassword"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="password" name="password2" id="password2" value="{$password2|escape}" password="true" maxlength="32" required="$passwordRequired">
					</label>
				</li>
			</ul>
		</fieldset>

		{* @todo Implement this without recourse to the Form Builder Vocab,
		    so we don't force themers to dip into FBV at all *}
		{include file="user/userGroups.tpl"}

		{* @todo recaptcha display is untested *}
		{if $reCaptchaHtml}
			<div class="recaptcha">
				{fieldLabel name="captcha" required=true key="common.captchaField" class="desc"}
				{$reCaptchaHtml}
			</div>
		{/if}

		<fieldset class="buttons">
			<button class="submit" type="submit">
				{translate key="user.register"}
			</button>
		</fieldset>

		{if !$implicitAuth}
			<div class="required_label">
				{translate key="common.requiredField"}
			</div>
		{/if}

		{if $privacyStatement}
			<div class="privacy">
				<h3>
					{translate key="user.register.privacyStatement"}
				</h3>
				<p>
					{$privacyStatement|nl2br}
				</p>
			</div>
		{/if}
	</form>
</div><!-- .page -->

{include file="common/frontend/footer.tpl"}
