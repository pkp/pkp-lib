{**
 * templates/frontend/components/navigationMenu.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Primary navigation menu list for OJS
 *}


<ul id="{$navigationMenu->getTitle()}" class="pkp_navigation_primary pkp_nav_list">
	{if $currentJournal}
        {foreach key=field item=navigationMenuItem from=$navigationMenu->navigationMenuItems}
            {if !empty($navigationMenuItem->navigationMenuItems)}
                <li aria-haspopup="true" aria-expanded="false">
			        <a href="{url router=$smarty.const.ROUTE_PAGE page=$navigationMenuItem->getPage() op="view" path=$navigationMenuItem->getPath()}">
				        {$navigationMenuItem->getLocalizedTitle()}
			        </a>
                    <ul>
                    {foreach key=childField item=childNavigationMenuItem from=$navigationMenuItem->navigationMenuItems}
                        <li>
					        <a href="{url router=$smarty.const.ROUTE_PAGE page=$childNavigationMenuItem->getPage() op="view" path=$childNavigationMenuItem->getPath()}">
				                {$childNavigationMenuItem->getLocalizedTitle()}
			                </a>
				        </li>
                    {/foreach}
			        </ul>
                </li>
            {else}
                <li>
				    <a href="{url router=$smarty.const.ROUTE_PAGE page=$navigationMenuItem->getPage() op="view" path=$navigationMenuItem->getPath()}">
				        {$navigationMenuItem->getLocalizedTitle()}
			        </a>
			    </li>
            {/if}
		{/foreach}
	{/if}
</ul>
