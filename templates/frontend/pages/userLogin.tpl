{**
 * templates/frontend/pages/userLogin.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User login form.
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="user.login"}

<div class="page page_login">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="user.login"}
	<h1>
		{translate key="user.login"}
	</h1>

	<p>
		{translate key="common.requiredField"}
	</p>
	{* A login message may be displayed if the user was redireceted to the
	   login page from another request. Examples include if login is required
	   before dowloading a file. *}
	{if $loginMessage}
		<p>
			{translate key=$loginMessage}
		</p>
	{/if}

	<form class="cmp_form cmp_form login" id="login" method="post" action="{$loginUrl}">
		{csrf}

		{if $error}
			<div class="pkp_form_error">
				{translate key=$error reason=$reason}
			</div>
		{/if}

		<input type="hidden" name="source" value="{$source|default:""|escape}" />

		<fieldset class="fields">
			<legend class="pkp_screen_reader">{translate key="user.login"}</legend>
			<div class="username">
				<label>
					<span class="label">
						{translate key="user.username"}
						<span class="required" aria-hidden="true">*</span>
						<span class="pkp_screen_reader">
							{translate key="common.required"}
						</span>
					</span>
					<input type="text" name="username" id="username" value="{$username|default:""|escape}" maxlength="32" required aria-required="true">
				</label>
			</div>
			<div class="password">
				<label>
					<span class="label">
						{translate key="user.password"}
						<span class="required" aria-hidden="true">*</span>
						<span class="pkp_screen_reader">
							{translate key="common.required"}
						</span>
					</span>
					<input type="password" name="password" id="password" value="{$password|default:""|escape}" password="true" maxlength="32" required aria-required="true">
					<a href="{url page="login" op="lostPassword"}">
						{translate key="user.login.forgotPassword"}
					</a>
				</label>
			</div>
			<div class="remember checkbox">
				<label>
					<input type="checkbox" name="remember" id="remember" value="1" checked="$remember">
					<span class="label">
						{translate key="user.login.rememberUsernameAndPassword"}
					</span>
				</label>
			</div>
			{* recaptcha spam blocker *}
			{if $reCaptchaHtml}
				<div class="captcha">
					<div class="pkp_form_locale_field">
						{$reCaptchaHtml}
					</div>
				</div>
			{/if}
			<div class="buttons">
				<button class="submit" type="submit">
					{translate key="user.login"}
				</button>

				{if !$disableUserReg}
					{capture assign=registerUrl}{url page="user" op="register" source=$source}{/capture}
					<a href="{$registerUrl}" class="register">
						{translate key="user.login.registerNewAccount"}
					</a>
				{/if}
			</div>
		</fieldset>
	</form>
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
