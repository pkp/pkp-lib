{**
 * templates/frontend/components/navigationMenu.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Primary navigation menu list for OJS
 *}

{if $navigationMenu}
    <nav id="{$navigationMenu->getTitle()}-navMenu" class="{$navClass} navDropdownMenu">
        <ul id="{$navigationMenu->getTitle()}-navMenuUL" class="{$ulClass} pkp_nav_list">
            {foreach key=field item=navigationMenuItemAssignment from=$navigationMenu->menuTree}
                {if !empty($navigationMenuItemAssignment->children)}
                    <li aria-haspopup="true" aria-expanded="false">
			            <a href="{url router=$smarty.const.ROUTE_PAGE page=$navigationMenuItemAssignment->navigationMenuItem->getPage() op=$navigationMenuItemAssignment->navigationMenuItem->getOp() path=$navigationMenuItemAssignment->navigationMenuItem->getPath()}">
				            {$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			            </a>
                        <ul>
                        {foreach key=childField item=childNavigationMenuItemAssignment from=$navigationMenuItemAssignment->children}
                            <li>
					            <a href="{url router=$smarty.const.ROUTE_PAGE page=$childNavigationMenuItemAssignment->navigationMenuItem->getPage() op=$childNavigationMenuItemAssignment->navigationMenuItem->getOp() path=$childNavigationMenuItemAssignment->navigationMenuItem->getPath()}">
				                    {$childNavigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			                    </a>
				            </li>
                        {/foreach}
			            </ul>
                    </li>
                {else}
                    <li>
				        <a href="{url router=$smarty.const.ROUTE_PAGE page=$navigationMenuItemAssignment->navigationMenuItem->getPage() op=$navigationMenuItemAssignment->navigationMenuItem->getOp() path=$navigationMenuItemAssignment->navigationMenuItem->getPath()}">
				            {$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
			            </a>
			        </li>
                {/if}
	        {/foreach}
        </ul>
    </nav>
{/if}