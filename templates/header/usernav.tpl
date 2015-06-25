{**
 * templates/header/usernav.tpl
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
    {if $multipleContexts}
        <li class="journals has-submenu">
            <a href="#">
                {translate key="navigation.switchJournals"}
            </a>
            <ul>
                {foreach from=$contextsNameAndUrl key=url item=name}
                    <li>
                        <a href="{$url}">
                            {$name}
                        </a>
                    </li>
                {/foreach}
            </ul>
        </li>
    {/if}
    {if $currentJournal}
        {url|assign:"homeUrl" page="index" router=$smarty.const.ROUTE_PAGE}
    {elseif $multipleContexts}
        {url|assign:"homeUrl" journal="index" router=$smarty.const.ROUTE_PAGE}
    {/if}
    {if $homeUrl}
        <li class="view-frontend">
            <a href="{$homeUrl}">
                {translate key="navigation.viewFrontend"}
            </a>
        </li>
    {/if}
	{if $isUserLoggedIn}
		{if array_intersect(array(ROLE_ID_SITE_ADMIN), $userRoles)}
		<li>
			<a href="{if $multipleContexts}{url router=$smarty.const.ROUTE_PAGE context="index" page="admin" op="index"}{else}{url router=$smarty.const.ROUTE_PAGE page="admin" op="index"}{/if}">
				{translate key="navigation.admin"}
			</a>
		</li>
		{/if}
		{if $isUserLoggedInAs}
			<li>
				<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOutAsUser"}">
					{translate key="user.logOutAs"} {$loggedInUsername|escape}
				</a>
			</li>
        {else}
		<li>
				<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOut"}">
					{translate key="user.logOut"}
				</a>
			</li>
		{/if}
	{/if}
</ul>
