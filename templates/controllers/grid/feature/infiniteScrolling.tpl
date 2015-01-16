{**
 * templates/controllers/grid/feature/infiniteScrolling.tpl
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Grid infinite scrolling markup.
 *}

<span class="gridPagingScrolling">
		{translate key="navigation.items.shownTotal" shown=$shown total=$iterator->getCount()}
</span>
