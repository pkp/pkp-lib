{**
 * templates/frontend/components/pagination.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Common template for displaying pagination
 *
 * @uses $prevUrl string URL to the previous page
 * @uses $nextUrl string URL to the next page
 * @uses $showingStart int The number of the first item shown on this page
 * @uses $showingEnd int The number of the last item shown on this page
 * @uses $total int The total number of items available
 *}

{if $prevUrl || $nextUrl}
	<div class="cmp_pagination" aria-label="{translate|escape key="common.pagination.label"}">
		{if $prevUrl}
			<a class="prev" href="{$prevUrl}">{translate key="help.previous"}</a>
		{/if}
		<span class="current">
			{translate key="common.pagination" start=$showingStart end=$showingEnd total=$total}
		</span>
		{if $nextUrl}
			<a class="next" href="{$nextUrl}">{translate key="help.next"}</a>
		{/if}
	</div>
{/if}
