{**
 * templates/header/sitenav.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Site-Wide Navigation Bar
 *}

<div class="pkp_structure_head_siteNav">
	<ul class="pkp_helpers_flatlist pkp_helpers_align_left">
		{if $isUserLoggedIn}
			{if array_intersect(array(ROLE_ID_SITE_ADMIN), $userRoles)}
				<li><a href="{if $multipleContexts}{url context="index" page="admin" op="index"}{else}{url page="admin" op="index"}{/if}">{translate key="navigation.admin"}</a></li>
			{/if}
		{/if}
		{if $multipleContexts}
			<li>{include file="header/contextSwitcher.tpl"}</li>
		{/if}
	</ul>
	<ul class="pkp_helpers_flatlist pkp_helpers_align_right">
		{if $isUserLoggedIn}
			<li class="profile">{translate key="user.hello"}&nbsp;<a href="{url page="user" op="profile"}">{$loggedInUsername|escape}</a></li>
			<li>{null_link_action id="toggleHelp" key="help.toggleInlineHelpOn"}</li>
			<li><a href="{url page="login" op="signOut"}">{translate key="user.logOut"}</a></li>
			{if $isUserLoggedInAs}
				<li><a href="{url page="login" op="signOutAsUser"}">{translate key="user.logOutAs"} {$loggedInUsername|escape}</a></li>
			{/if}
		{elseif !$notInstalled}
			{if !$hideRegisterLink}
				<li><a disabled="disabled" href="{url page="user" op="register"}">{translate key="navigation.register"}</a></li>
			{/if}
			<li><a disabled="disabled" href="{url page="login"}">{translate key="navigation.login"}</a></li>
		{/if}
	</ul>
</div>
