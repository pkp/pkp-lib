{**
 * templates/header/frontend/usernav.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Site-Wide Navigation Bar
 *}
<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#navigationUser').pkpHandler(
				'$.pkp.controllers.MenuHandler');
	{rdelim});
 </script>
<ul id="navigationUser" class="pkp_navigation_user pkp_nav_list">
	{if $isUserLoggedIn}
        <li>
            {include file="controllers/page/tasks.tpl"}
        </li>
		<li class="profile has-submenu">
			<a href="{url router=$smarty.const.ROUTE_PAGE page="dashboard"}">{$loggedInUsername|escape}</a>
			<ul>
				{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR), $userRoles)}
					<li>
						<a href="{url router=$smarty.const.ROUTE_PAGE page="dashboard"}">
							{translate key="navigation.dashboard"}
						</a>
					</li>
				{/if}
				<li>
					<a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="profile"}">
						{translate key="common.viewProfile"}
					</a>
				</li>
				{if array_intersect(array(ROLE_ID_SITE_ADMIN), $userRoles)}
				<li>
					<a href="{if $multipleContexts}{url router=$smarty.const.ROUTE_PAGE context="index" page="admin" op="index"}{else}{url router=$smarty.const.ROUTE_PAGE page="admin" op="index"}{/if}">
						{translate key="navigation.admin"}
					</a>
				</li>
				{/if}
				<li>
					{null_link_action id="toggleHelp" key="help.toggleInlineHelpOn"}
				</li>
				<li>
					<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOut"}">
						{translate key="user.logOut"}
					</a>
				</li>
				{if $isUserLoggedInAs}
					<li>
						<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOutAsUser"}">
							{translate key="user.logOutAs"} {$loggedInUsername|escape}
						</a>
					</li>
				{/if}
			</ul>
		</li>
	{elseif !$notInstalled}
		{if !$hideRegisterLink}
			<li><a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="register"}">{translate key="navigation.register"}</a></li>
		{/if}
		<li><a href="{url router=$smarty.const.ROUTE_PAGE page="login"}">{translate key="navigation.login"}</a></li>
	{/if}
</ul>
