{**
 * templates/frontend/pages/userLogin.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User login form.
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="user.login"}

<div class="page page_login">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="user.login"}

	{* A login message may be displayed if the user was redireceted to the
	   login page from another request. Examples include if login is required
	   before dowloading a file. *}
	{if $loginMessage}
		<p>
			{translate key=$loginMessage}
		</p>
	{/if}

	{if $implicitAuth}
		<a id="implicitAuthLogin" href="{url page="login" op="implicitAuthLogin"}">Login</a>
	{else}
		<script>
			$(function() {ldelim}
				// Attach the form handler.
				$('#login').pkpHandler(
					'$.pkp.controllers.form.FormHandler',
					{ldelim}
						trackFormChanges: false
					{rdelim});
			{rdelim});
		</script>

		<form class="pkp_form login" id="login" method="post" action="{$loginUrl}">

			{if $error}
				<div class="pkp_form_error">
					{translate key=$error reason=$reason}
				</div>
			{/if}

			<input type="hidden" name="source" value="{$source|strip_unsafe_html|escape}" />

			<fieldset class="fields">
				<div class="username">
					<label>
						<span class="label">
							{translate key="user.username"}
						</span>
						<input type="text" name="username" id="username" value="{$username|escape}" maxlength="32" required tabindex="1">
					</label>
				</div>
				<div class="password">
					<label>
						<span class="label">
							{translate key="user.password"}
						</span>
						<input type="password" name="password" id="password" value="{$password|escape}" password="true" maxlength="32" required="$passwordRequired" tabindex="2">
						<a href="{url page="login" op="lostPassword"}" tabindex="3">
							{translate key="user.login.forgotPassword"}
						</a>
					</label>
				</div>
				<div class="remember checkbox">
					<label>
						<input type="checkbox" name="remember" id="remember" value="1" checked="$remember" tabindex="4">
						<span class="label">
							{translate key="user.login.rememberUsernameAndPassword"}
						</span>
					</label>
				</div>
			</fieldset>

			<div class="buttons">
				<button class="submit" type="submit">
					{translate key="user.login"}
				</button>

				{if !$disableUserReg}
					{url|assign:registerUrl page="user" op="register" source=$source}
					<a href="{$registerUrl}" class="register">
						{translate key="user.login.registerNewAccount"}
					</a>
				{/if}
			</div>

		</form>
	{/if}{* !$implicitAuth *}

</div><!-- .page -->

{include file="common/frontend/footer.tpl"}
