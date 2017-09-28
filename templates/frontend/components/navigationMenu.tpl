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
	<ul id="{$navigationMenu->getTitle()}-navMenuUL" class="{$ulClass} pkp_nav_list">
		{foreach key=field item=navigationMenuItemAssignment from=$navigationMenu->menuTree}
			{if !empty($navigationMenuItemAssignment->children)}
				{if $navigationMenuItemAssignment->navigationMenuItem->getIsDisplayed()}
					<li class="{$liClass} ">
						<a id="{$field}-ancor-dropDown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" href="#">
							{$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
						</a>
						<ul class="dropdown-menu" aria-labelledby="{$field}-ancor-dropDown">
						{foreach key=childField item=childNavigationMenuItemAssignment from=$navigationMenuItemAssignment->children}
							{if $childNavigationMenuItemAssignment->navigationMenuItem->getIsDisplayed()}
								<li>
									<a href="{$childNavigationMenuItemAssignment->navigationMenuItem->getUrl()}">
										{$childNavigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
									</a>
								</li>
							{/if}
						{/foreach}
						</ul>
					</li>
				{/if}
			{else}
				{if $navigationMenuItemAssignment->navigationMenuItem->getIsDisplayed()}
					<li class="{$liClass} ">
						<a href="{$navigationMenuItemAssignment->navigationMenuItem->getUrl()}">
							{$navigationMenuItemAssignment->navigationMenuItem->getLocalizedTitle()}
						</a>
					</li>
				{/if}
			{/if}
		{/foreach}
	</ul>
{/if}