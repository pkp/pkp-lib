{**
 * templates/frontend/components/navigationMenu.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Primary navigation menu list for the application
 *
 * @uses navigationMenu array Hierarchical array of navigation menu item assignments
 * @uses id string Element ID to assign the outer <ul>
 * @uses ulClass string Class name(s) to assign the outer <ul>
 * @uses liClass string Class name(s) to assign all <li> elements
 *}

{if $navigationMenu}
	<ul id="{$id|escape}" class="{$ulClass|escape} pkp_nav_list">
		{foreach key=field item=navigationMenuItemAssignment from=$navigationMenu->menuTree}
			{if !$navigationMenuItemAssignment->navigationMenuItem->getIsDisplayed()}
				{continue}
			{/if}

			{* Check if menu item has submenu *}
			{if $navigationMenuItemAssignment->navigationMenuItem->getIsChildVisible()}
				{assign var=hasSubmenu value=true}
			{else}
				{assign var=hasSubmenu value=false}
			{/if}
			{* Check if is current page for aria-current parameter*}
			{assign var="isActive" value={default_item_active item=$navigationMenuItemAssignment->navigationMenuItem}}

		<li class="{$liClass|escape} main-menu__nav-item{if $hasSubmenu} dropdown{/if} {default_item_active item=$navigationMenuItemAssignment->navigationMenuItem}" >
				<a {if $isActive} aria-current="page" {/if}{if $hasSubmenu} aria-current="true"{/if} href="{$navigationMenuItemAssignment->navigationMenuItem->getUrl()}">
					{$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
				</a>
				{if $navigationMenuItemAssignment->navigationMenuItem->getIsChildVisible()}
					<ul>
						{foreach key=childField item=childNavigationMenuItemAssignment from=$navigationMenuItemAssignment->children}
							{assign var="isActive2" value={default_item_active item=$childNavigationMenuItemAssignment->navigationMenuItem}}
							{if $childNavigationMenuItemAssignment->navigationMenuItem->getIsDisplayed()}
								<li class="{$liClass|escape} {default_item_active item=$childNavigationMenuItemAssignment->navigationMenuItem}">
									<a href="{$childNavigationMenuItemAssignment->navigationMenuItem->getUrl()}" {if $isActive2} aria-current="page" {/if} >
										{$childNavigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
									</a>
								</li>
							{/if}
						{/foreach}
					</ul>
				{/if}
			</li>
		{/foreach}
	</ul>
{/if}
