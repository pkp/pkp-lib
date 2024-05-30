{**
 * templates/frontend/components/archiveHeader.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Archive header containing a search form and a category listing
 *}

 <section class="archiveHeader">
	{* Search *}
	<section class="archiveHeader_search">
		{include file="frontend/components/searchForm_archive.tpl" className="pkp_search_desktop"}
	</section>

	{* Categories listing *}
	<section class="archiveHeader_categories">
	<ul class="categories_listing">
		{foreach from=$categories item=category}
			{if !$category->getParentId()}
				<li class="category_{$category->getPath()|escape}">
					<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="preprints" op="category" path=$category->getPath()|escape}">
						{$category->getLocalizedTitle()|escape}
					</a>
				</li>
			{/if}
		{/foreach}
	</ul>
	</section>
</section>
