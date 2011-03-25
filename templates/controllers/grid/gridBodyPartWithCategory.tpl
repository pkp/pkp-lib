{**
 * temlates/controllers/grid/gridBodyPartWithCategory.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a set of grid rows with a category row at the beginning
 *}
{** category id must be set by the rendering of the catgory row **}
<tbody id="{$categoryId|escape}">
	<tr class="category{if $iterator} group{$iterator|escape}{/if}">
		{$renderedCategoryRow}
		{** the regular data rows **}
		{foreach from=$rows item=row}
			{$row}
		{/foreach}
	</tr>
</tbody>

