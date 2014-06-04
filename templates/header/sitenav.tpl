{**
 * templates/header/sitenav.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Site-Wide Navigation Bar
 *}
<div class="pkp_structure_head_siteNav">
	<ul class="pkp_helpers_flatlist pkp_helpers_align_left">
		{if $isUserLoggedIn}
			{if array_intersect(array(ROLE_ID_SITE_ADMIN), $userRoles)}
				<li><a href="{if $multipleContexts}{url router=$smarty.const.ROUTE_PAGE context="index" page="admin" op="index"}{else}{url router=$smarty.const.ROUTE_PAGE page="admin" op="index"}{/if}">{translate key="navigation.admin"}</a></li>
			{/if}
		{/if}
		{if $multipleContexts}
			<li>{include file="header/contextSwitcher.tpl"}</li>
		{/if}
	</ul>
	{if $isUserLoggedIn}
		<div class="notifications_popover" style="display: none;">
			{url|assign:notificationsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.notifications.NotificationsGridHandler" op="fetchGrid" escape=false}
			{load_url_in_div id="notificationsGrid" url=$notificationsGridUrl}
		</div>
	{/if}
	<ul class="pkp_helpers_flatlist pkp_helpers_align_right">
		{if $isUserLoggedIn}
			<li class="profile">{translate key="user.hello"}&nbsp;<a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="profile"}">{$loggedInUsername|escape}</a></li>
			<li class="notificationsLinkContainer"><a href="#" id="notificationsToggle">{translate key="common.tasks"}</a></li>
			<li>{null_link_action id="toggleHelp" key="help.toggleInlineHelpOn"}</li>
			<li><a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOut"}">{translate key="user.logOut"}</a></li>
			{if $isUserLoggedInAs}
				<li><a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOutAsUser"}">{translate key="user.logOutAs"} {$loggedInUsername|escape}</a></li>
			{/if}
		{elseif !$notInstalled}
			{if !$hideRegisterLink}
				<li><a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="register"}">{translate key="navigation.register"}</a></li>
			{/if}
			<li><a href="{url router=$smarty.const.ROUTE_PAGE page="login"}">{translate key="navigation.login"}</a></li>
		{/if}
	</ul>
</div>
