{**
 * templates/user/login.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User login form.
 *
 *}
{strip}
{assign var="pageTitle" value="user.login"}
{include file="common/header.tpl"}
{/strip}

{if !$registerOp}
	{assign var="registerOp" value="register"}
{/if}
{if !$registerLocaleKey}
	{assign var="registerLocaleKey" value="user.login.registerNewAccount"}
{/if}

{if $loginMessage}
	<span class="instruct">{translate key="$loginMessage"}</span>
	<br />
	<br />
{/if}

{if $implicitAuth}
	<a id="implicitAuthLogin" href="{url page="login" op="implicitAuthLogin"}">Login</a>
{else}
	<form id="signinForm" method="post" action="{$loginUrl}">
{/if}

{if $error}
	<span class="pkp_form_error">{translate key="$error" reason=$reason}</span>
	<br />
	<br />
{/if}

<input type="hidden" name="source" value="{$source|strip_unsafe_html|escape}" />

{if ! $implicitAuth}
	<table id="signinTable" class="data">
	<tr>
		<td class="label"><label for="loginUsername">{translate key="user.username"}</label></td>
		<td class="value"><input type="text" id="loginUsername" name="username" value="{$username|escape}" size="20" maxlength="32" class="textField" /></td>
	</tr>
	<tr>
		<td class="label"><label for="loginPassword">{translate key="user.password"}</label></td>
		<td class="value"><input type="password" id="loginPassword" name="password" value="{$password|escape}" size="20" class="textField" /></td>
	</tr>
	{if $showRemember}
	<tr valign="middle">
		<td></td>
		<td class="value"><input type="checkbox" id="loginRemember" name="remember" value="1"{if $remember} checked="checked"{/if} /> <label for="loginRemember">{translate key="user.login.rememberUsernameAndPassword"}</label></td>
	</tr>
	{/if}{* $showRemember *}

	<tr>
		<td></td>
		<td><input type="submit" value="{translate key="user.login"}" class="button" /></td>
	</tr>
	</table>

	<ul>
		{if !$hideRegisterLink}<li><a href="{url page="user" op=$registerOp}">{translate key=$registerLocaleKey}</a></li>{/if}
		<li><a href="{url page="login" op="lostPassword"}">{translate key="user.login.forgotPassword"}</a></li>
	</ul>

<script type="text/javascript">
<!--
	document.getElementById('{if $username}loginPassword{else}loginUsername{/if}').focus();
// -->
</script>
{/if}{* !$implicitAuth *}

</form>

{include file="common/footer.tpl"}
