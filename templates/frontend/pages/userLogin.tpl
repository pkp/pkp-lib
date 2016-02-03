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
	{/if}

	{if $error}
		<div class="pkp_form_error">
			{translate key=$error reason=$reason}
		</div>
	{/if}

		<input type="hidden" name="source" value="{$source|strip_unsafe_html|escape}" />

		{if !$implicitAuth}

			<ul class="fields">
				<li class="username">
					<label>
						<span class="label">
							{translate key="user.username"}
						</span>
						<input type="text" name="username" id="username" value="{$username|escape}" maxlength="32" required>
					</label>
				</li>
				<li class="password">
					<label>
						<span class="label">
							{translate key="user.password"}
							<a href="{url page="login" op="lostPassword"}">
								{translate key="user.login.forgotPassword"}
							</a>
						</span>
						<input type="password" name="password" id="password" value="{$password|escape}" password="true" maxlength="32" required="$passwordRequired">
					</label>
				</li>
				<li class="remember checkbox">
					<label>
						<input type="checkbox" name="remember" id="remember" value="1" checked="$remember">
						<span class="label">
							{translate key="user.login.rememberUsernameAndPassword"}
						</span>
					</label>
				</li>
			</ul>

			<div class="buttons">
				<button class="submit" type="submit">
					{translate key="user.login"}
				</button>

				{if !$hideRegisterLink}
					{url|assign:registerUrl page="user" op="register" source=$source}
					<a href="{$registerUrl}" class="register">
						{translate key="user.login.registerNewAccount"}
					</a>
				{/if}
			</div>

			{* {fbvFormArea id="loginFields"}
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
				{/if}
				{if !$hideRegisterLink}
					{if $source}
						{url|assign:cancelUrl page="user" op="register" source=$source escape=false}
					{else}
						{url|assign:cancelUrl page="user" op="register" escape=false}
					{/if}
					{fbvFormButtons cancelUrl=$cancelUrl cancelText="user.login.registerNewAccount" submitText="user.login"}
				{else}
					{fbvFormButtons hideCancel=true submitText="user.login.resetPassword"}
				{/if}
			{/fbvFormArea} *}

		{/if}{* !$implicitAuth *}
	</form>

</div><!-- .page -->

{include file="common/frontend/footer.tpl"}
