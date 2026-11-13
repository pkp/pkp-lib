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
 * @uses navigationMenuCurrentUrl string URL of the page being viewed, without a query string
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
			{assign var=navigationMenuItemUrl value=$navigationMenuItemAssignment->navigationMenuItem->getUrl()}
			{assign var=navigationMenuItemPath value=$navigationMenuItemUrl|regex_replace:"/[\?#].*$/":""}
			<li class="{$liClass|escape}">
				<a href="{$navigationMenuItemUrl}"{if $navigationMenuItemPath == $navigationMenuCurrentUrl} aria-current="page"{/if}>
					{$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
				</a>
				{if $navigationMenuItemAssignment->navigationMenuItem->getIsChildVisible()}
					<ul>
						{foreach key=childField item=childNavigationMenuItemAssignment from=$navigationMenuItemAssignment->children}
							{if $childNavigationMenuItemAssignment->navigationMenuItem->getIsDisplayed()}
								{assign var=childNavigationMenuItemUrl value=$childNavigationMenuItemAssignment->navigationMenuItem->getUrl()}
								{assign var=childNavigationMenuItemPath value=$childNavigationMenuItemUrl|regex_replace:"/[\?#].*$/":""}
								<li class="{$liClass|escape}">
									<a href="{$childNavigationMenuItemUrl}"{if $childNavigationMenuItemPath == $navigationMenuCurrentUrl} aria-current="page"{/if}>
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
