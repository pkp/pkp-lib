{**
 * templates/frontend/components/navigationMenuUser.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User navigation menu list 
 *}

{if $navigationMenu}
    <nav id="{$navigationMenu->getTitle()}-navMenu" class="{$navClass} navDropdownMenu">
        <ul id="{$navigationMenu->getTitle()}-navMenuUL" class="{$ulClass} pkp_nav_list">
		    {if $isUserLoggedIn}
			    <li class="profile {if $unreadNotificationCount} has_tasks{/if}" aria-haspopup="true" aria-expanded="false">
				    <a href="{url router=$smarty.const.ROUTE_PAGE page="submissions"}">
					    {$loggedInUsername|escape}
					    <span class="task_count">
						    {$unreadNotificationCount}
					    </span>
				    </a>
				    <ul>
					    {if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR), (array)$userRoles)}
						    <li>
							    <a href="{url router=$smarty.const.ROUTE_PAGE page="submissions"}">
								    {translate key="navigation.dashboard"}
								    <span class="task_count">
									    {$unreadNotificationCount}
								    </span>
							    </a>
						    </li>
					    {/if}
					    <li>
						    <a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="profile"}">
							    {translate key="common.viewProfile"}
						    </a>
					    </li>
					    {if array_intersect(array(ROLE_ID_SITE_ADMIN), (array)$userRoles)}
					    <li>
						    <a href="{if $multipleContexts}{url router=$smarty.const.ROUTE_PAGE context="index" page="admin" op="index"}{else}{url router=$smarty.const.ROUTE_PAGE page="admin" op="index"}{/if}">
							    {translate key="navigation.admin"}
						    </a>
					    </li>
					    {/if}
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
		    {else}
			    {if !$disableUserReg}
				    <li><a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="register"}">{translate key="navigation.register"}</a></li>
			    {/if}
			    <li><a href="{url router=$smarty.const.ROUTE_PAGE page="login"}">{translate key="navigation.login"}</a></li>
		    {/if}
            {foreach key=field item=navigationMenuItemAssignment from=$navigationMenu->menuTree}
                {if !empty($navigationMenuItemAssignment->children)}
                    <li aria-haspopup="true" aria-expanded="false">
                        {if $navigationMenuItemAssignment->navigationMenuItem->getUseCustomUrl()}
                            <a href="{$navigationMenuItemAssignment->navigationMenuItem->getCustomUrl()}">
				                {$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                </a>
                        {else}
                            <a href="{url router=$smarty.const.ROUTE_PAGE page=$navigationMenuItemAssignment->navigationMenuItem->getPage() op=$navigationMenuItemAssignment->navigationMenuItem->getOp() path=$navigationMenuItemAssignment->navigationMenuItem->getPath()}">
				                {$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                </a>
                        {/if}
                        <ul>
                        {foreach key=childField item=childNavigationMenuItemAssignment from=$navigationMenuItemAssignment->children}
                            <li>
                                {if $childNavigationMenuItemAssignment->navigationMenuItem->getUseCustomUrl()}
                                    <a href="{$childNavigationMenuItemAssignment->navigationMenuItem->getCustomUrl()}">
				                        {$childNavigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                        </a>
                                {else}
                                    <a href="{url router=$smarty.const.ROUTE_PAGE page=$childNavigationMenuItemAssignment->navigationMenuItem->getPage() op=$childNavigationMenuItemAssignment->navigationMenuItem->getOp() path=$childNavigationMenuItemAssignment->navigationMenuItem->getPath()}">
				                        {$childNavigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                        </a>
                                {/if}
				            </li>
                        {/foreach}
			            </ul>
                    </li>
                {else}
                    <li>
                        {if $navigationMenuItemAssignment->navigationMenuItem->getUseCustomUrl()}
                            <a href="{$navigationMenuItemAssignment->navigationMenuItem->getCustomUrl()}">
				                {$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                </a>
                        {else}
                            <a href="{url router=$smarty.const.ROUTE_PAGE page=$navigationMenuItemAssignment->navigationMenuItem->getPage() op=$navigationMenuItemAssignment->navigationMenuItem->getOp() path=$navigationMenuItemAssignment->navigationMenuItem->getPath()}">
				                {$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                </a>
                        {/if}
				        
			        </li>
                {/if}
	        {/foreach}
	    </ul>
    </nav>
{/if}